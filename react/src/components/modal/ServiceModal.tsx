import { Modal, Form, Input, Select, DatePicker, Button, InputNumber, AutoComplete, message } from 'antd';
import { useEffect, useMemo, useRef, useState } from 'react';
import dayjs, { Dayjs } from 'dayjs';
import customParseFormat from 'dayjs/plugin/customParseFormat';
import { useModalStore } from '../../store/modalStore';
import api from '../../../axiosConfig';

dayjs.extend(customParseFormat);

type CustomerRow = { id: number; name: string; phone?: string | null };

interface FormValues {
  service_name: string;
  customer_name: string;
  customer_id?: number | null;
  phone_number?: string;

  service_price: number;
  expense?: number;
  debt?: number;

  service_date: Dayjs;
  warranty: number; // tháng (0..12)
  note?: string;
}

const parseNumber = ((v?: string) => {
  const s = String(v ?? '').replace(/,/g, '');
  return s ? Number(s) : undefined;
}) as any;

const parseServiceDate = (v?: unknown): Dayjs => {
  if (!v) return dayjs();
  if (dayjs.isDayjs(v)) return v as Dayjs;

  const s = String(v).trim();
  const formats = [
    'YYYY-MM-DD',
    'YYYY/MM/DD',
    'YYYY-MM-DD HH:mm:ss',
    'YYYY/MM/DD HH:mm:ss',
    'DD/MM/YYYY',
    'DD-MM-YYYY',
  ];
  for (const f of formats) {
    const d = dayjs(s, f, true);      // strict
    if (d.isValid()) return d;
  }
  const loose = dayjs(s);             // fallback “thoáng”
  return loose.isValid() ? loose : dayjs();
};

const toYMD = (v?: Dayjs | string | null) =>
  v ? (dayjs.isDayjs(v) ? v.format('YYYY-MM-DD') : dayjs(v).format('YYYY-MM-DD')) : null;

const monthsFromLabel = (label?: string) => {
  if (!label) return 0;
  if (label.toLowerCase().includes('không')) return 0;
  const m = parseInt(label.replace(/\D+/g, ''), 10);
  return Number.isFinite(m) ? m : 0;
};

export default function ServiceModal() {
  // ✅ lấy đúng slice service (isOpen, isEdit, record, open, close)
  const modal = useModalStore(s => s.service);

  const [form] = Form.useForm<FormValues>();
  const [loading, setLoading] = useState(false);

  // input hiển thị trong AutoComplete (để value option unique mà input chỉ hiện tên)
  const [customerInput, setCustomerInput] = useState<string>("");

  // customer search state
  const [optionsLoading, setOptionsLoading] = useState(false);
  const [suggestions, setSuggestions] = useState<CustomerRow[]>([]);
  const [isExistingCustomer, setIsExistingCustomer] = useState(false);
  const searchTimer = useRef<number | null>(null);
  const bumpServicesVersion = useModalStore(s => s.bumpServicesVersion);

  // ===== Initial values (prefill khi SỬA) =====
  const initialValues: Partial<FormValues> = useMemo(() => {
    const r: any = modal.record || {};
    return {
      service_name: r.name ?? r.service_name ?? '',
      customer_name: r.customerName ?? r.customer_name ?? '',
      customer_id: null, // sẽ fill bằng GET /services/{id} khi edit (nếu có)
      phone_number: r.phone ?? r.phone_number ?? '',

      service_price: Number(r.price ?? r.service_price ?? 0),
      expense: Number(r.cost ?? r.expense ?? 0),
      debt: Number(r.debt ?? 0),

      service_date: parseServiceDate(r.service_date ?? r.date ?? ''),
      warranty: monthsFromLabel(r.warranty ?? ''),
      note: r.note ?? '',
    };
  }, [modal.record, modal.isEdit]);

  // 🔁 Hydrate form mỗi lần mở/đổi record (đảm bảo form đổ dữ liệu cũ)
  useEffect(() => {
    if (!modal.isOpen) return;
    form.resetFields();
    form.setFieldsValue(initialValues as any);
  }, [modal.isOpen, modal.record?.id]); // eslint-disable-line react-hooks/exhaustive-deps


  // ===== Customer search (giống MobileModal) =====
  const fetchCustomers = async (q: string) => {
    try {
      setOptionsLoading(true);
      const res = await api.get('/admin/customers', { params: { search: q, limit: 5 } });
      const rows: CustomerRow[] = (Array.isArray(res.data?.data) ? res.data.data : Array.isArray(res.data) ? res.data : []).map((c: any) => ({
        id: Number(c.id),
        name: c.name ?? c.fullname ?? c.customer_name ?? '',
        phone: c.phone ?? c.phone_number ?? null,
      }));
      setSuggestions(rows);

      const currentName = form.getFieldValue('customer_name')?.trim() || '';
      if (currentName) {
        const matched = rows.find(r => r.name.trim().toLowerCase() === currentName.toLowerCase());
        if (matched) {
          setIsExistingCustomer(true);
          form.setFieldsValue({ customer_id: matched.id, phone_number: matched.phone ?? '' });
          return;
        }
      }
      setIsExistingCustomer(false);
      form.setFieldsValue({ customer_id: null });
    } catch {
      setSuggestions([]);
    } finally {
      setOptionsLoading(false);
    }
  };

  const handleSearchName = (q: string) => {
    if (searchTimer.current) {
      window.clearTimeout(searchTimer.current);
      searchTimer.current = null;
    }
    searchTimer.current = window.setTimeout(() => {
      if (q.trim()) fetchCustomers(q.trim());
      else {
        setSuggestions([]);
        setIsExistingCustomer(false);
        form.setFieldsValue({ customer_id: null, phone_number: '' });
      }
    }, 350) as unknown as number;
  };

const handleSelectName = (val: string, option: any) => {
    // val: "Tên__#123"
    const [name, idPart] = String(val).split('__#');
    const id = idPart ? Number(idPart) : undefined;

    setCustomerInput(name);
    setIsExistingCustomer(true);
    form.setFieldsValue({
      customer_name: name,
      customer_id: id ?? null,
      phone_number: option?.phone ?? '',
    });
  };

  const handleChangeName = (text: string) => {
    setCustomerInput(text);
    const matched = suggestions.find(r => r.name.trim().toLowerCase() === text.trim().toLowerCase());
    if (matched) {
      setIsExistingCustomer(true);
      form.setFieldsValue({ customer_id: matched.id, phone_number: matched.phone ?? '' });
    } else {
      setIsExistingCustomer(false);
      form.setFieldsValue({ customer_id: null });
    }
  };

  const handleBlurName = () => {
    const val = customerInput.trim();
    setCustomerInput(val);
    if (!val) {
      setIsExistingCustomer(false);
      form.setFieldsValue({ customer_id: null, phone_number: '' , customer_name: ''});
      return;
    }
    const matched = suggestions.find(r => r.name.trim().toLowerCase() === val.toLowerCase());
    if (matched) {
      setIsExistingCustomer(true);
      form.setFieldsValue({ customer_id: matched.id, phone_number: matched.phone ?? '', customer_name: val });
    } else {
      setIsExistingCustomer(false);
      form.setFieldsValue({ customer_id: null, customer_name: val });
    }
  };

  const buildOptions = (list: CustomerRow[]) =>
  list.map(s => ({
    value: `${s.name}__#${s.id}`,
    label: `${s.name}${s.phone ? ` • ${s.phone}` : ''}`,
    id: s.id,
    phone: s.phone,
  }));

  // ===== Submit =====
  const handleSubmit = async (values: FormValues) => {
    setLoading(true);
    try {
      const payload: any = {
        // service
        name: values.service_name.trim(),
        price: Number(values.service_price ?? 0),
        expense: Number(values.expense ?? 0),
        warranty: Number(values.warranty ?? 0),

        // customer selection
        customer_id: values.customer_id ?? null,
        customer_name: values.customer_name.trim(),
        phone_number: values.phone_number?.trim() || null,

        // optional
        debt: Number(values.debt ?? 0),
        service_date: toYMD(values.service_date),
        note: values.note?.trim() || null,
      };

      if (modal.isEdit && modal.record?.id) {
        await api.put(`/services/${modal.record.id}`, payload);
        message.success('Cập nhật dịch vụ thành công');
      } else {
        await api.post('/services', payload);
        message.success('Thêm dịch vụ thành công');
      } 
      bumpServicesVersion();
      modal.close?.();
    } catch (e: any) {
      console.error(e);
      message.error(e?.response?.data?.message || 'Lỗi khi lưu dịch vụ');
    } finally {
      setLoading(false);
    }
  };

  const warrantyOptions = useMemo(
    () => Array.from({ length: 13 }, (_, i) => ({ value: i, label: i === 0 ? 'Không bảo hành' : `${i} tháng` })),
    []
  );

  const formKey = modal.isEdit ? `service-edit-${modal.record?.id ?? 'none'}` : 'service-create';

  return (
    <Modal
      title={modal.isEdit ? 'Sửa dịch vụ' : 'Thêm mới dịch vụ'}
      open={!!modal.isOpen}
      onCancel={modal.close}
      footer={null}
      maskClosable={false}
      destroyOnHidden
    >
      <Form<FormValues>
        key={formKey}
        form={form}
        layout="horizontal"
        labelCol={{ span: 6 }}
        wrapperCol={{ span: 18 }}
        initialValues={initialValues}
        onFinish={handleSubmit}
      >
        <Form.Item
          label="Tên dịch vụ"
          name="service_name"
          rules={[{ required: true, message: 'Vui lòng nhập tên dịch vụ' }]}
        >
          <Input />
        </Form.Item>

        {/* === Khách hàng (giống MobileModal) === */}
        <Form.Item
          label="Khách hàng"
          name="customer_name"
          rules={[{ required: true, message: 'Vui lòng nhập tên khách hàng' }]}
          tooltip="Gõ để tìm khách cũ; nếu trùng tên sẽ tự đổ SĐT; nếu không, hãy nhập SĐT mới"
        >
          <AutoComplete
            placeholder="Nhập tên khách hàng"
            value={customerInput}
            onSearch={handleSearchName}
            onSelect={handleSelectName}
            onChange={handleChangeName}
            onBlur={handleBlurName}
            notFoundContent={optionsLoading ? 'Đang tải...' : suggestions.length === 0 ? 'Không tìm thấy' : null}
            options={buildOptions(suggestions)}
            filterOption={(input, option) =>
              String(option?.label ?? '').toLowerCase().includes(input.toLowerCase())
            }
          />
        </Form.Item>

        {/* Ẩn customer_id để submit nếu trùng */}
        <Form.Item name="customer_id" hidden>
          <Input />
        </Form.Item>

        <Form.Item
          label="Số điện thoại"
          name="phone_number"
          rules={
            isExistingCustomer
              ? []
              : [
                  { required: true, message: 'Nhập số điện thoại' },
                  { pattern: /^[0-9+\s-]{8,15}$/, message: 'Số điện thoại không hợp lệ' },
                ]
          }
        >
          <Input
            placeholder={isExistingCustomer ? 'Tự động theo khách đã có' : 'Nhập số điện thoại mới'}
            disabled={isExistingCustomer}
          />
        </Form.Item>
        {/* === hết phần KH === */}

        <Form.Item
          label="Tiền dịch vụ"
          name="service_price"
          rules={[{ required: true, message: 'Vui lòng nhập tiền dịch vụ' }]}
        >
          <InputNumber
            min={0}
            style={{ width: '100%' }}
            formatter={(v) => String(v ?? '').replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
            parser={parseNumber}
          />
        </Form.Item>

        <Form.Item label="Chi phí" name="expense">
          <InputNumber
            min={0}
            style={{ width: '100%' }}
            formatter={(v) => String(v ?? '').replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
            parser={parseNumber}
          />
        </Form.Item>

          <Form.Item
            label="Nợ lại"
            name="debt"
            preserve={false}   // ✅ khi unmount sẽ xóa value khỏi form store
          >
            <InputNumber
              min={0}
              style={{ width: '100%' }}
              formatter={(v) => String(v ?? '').replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
              parser={parseNumber}
            />
          </Form.Item>

        <Form.Item label="Ngày bán" name="service_date">
          <DatePicker format="DD/MM/YYYY" style={{ width: '100%' }} placeholder="Chọn ngày" />
        </Form.Item>

        <Form.Item
          label="Bảo hành"
          name="warranty"
          rules={[{ required: true, message: 'Vui lòng chọn bảo hành' }]}
        >
          <Select options={warrantyOptions} allowClear showSearch optionFilterProp="label" />
        </Form.Item>

        <Form.Item label="Ghi chú" name="note">
          <Input.TextArea rows={3} />
        </Form.Item>

        <Form.Item style={{ display: 'flex', justifyContent: 'center', marginTop: 16 }}>
          <Button type="primary" htmlType="submit" loading={loading}>
            {modal.isEdit ? 'Lưu thay đổi' : 'Thêm mới'}
          </Button>
        </Form.Item>
      </Form>
    </Modal>
  );
}
