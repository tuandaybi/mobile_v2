import { Modal, Form, Input, DatePicker, Button, Radio, InputNumber, message, Select, AutoComplete } from 'antd';
import { useMemo, useState, useEffect, useRef } from 'react';
import dayjs, { Dayjs } from 'dayjs';
import customParseFormat from 'dayjs/plugin/customParseFormat';
import { useModalStore } from '../../store/modalStore';
import api from '../../../axiosConfig';

dayjs.extend(customParseFormat);

type Payment = '0' | '1' | '2'; // 0: chuyển khoản, 1: trả góp, 2: tiền mặt

interface FormValues {
  customer_name: string;          // text người dùng gõ
  customer_id?: number | null;    // nếu trùng tên khách cũ
  phone_number?: string;

  export_price: number;
  expense?: number;
  debt?: number;
  payment: Payment;
  export_date: Dayjs;             // luôn là Dayjs
  warranty: number;
  note?: string;
}

type CustomerRow = { id: number; name: string; phone?: string };

const toNumber = (v: any, d = 0) => (v === undefined || v === null || v === '' ? d : Number(v));
const parseNumber = ((v?: string) => {
  const s = String(v ?? '').replace(/,/g, '');
  return s ? Number(s) : undefined;
}) as any;

const parseDate = (d?: string | Dayjs | null) => {
  if (!d) return null;
  if (dayjs.isDayjs(d)) return d;
  const p = dayjs(String(d), ['YYYY-MM-DD', 'DD/MM/YYYY'], true);
  return p.isValid() ? p : null;
};

const toYMD = (v?: Dayjs | string | null) =>
  v ? (dayjs.isDayjs(v) ? v.format('YYYY-MM-DD') : dayjs(v, ['YYYY-MM-DD','DD/MM/YYYY'], true).format('YYYY-MM-DD')) : null;

export default function SellMobileModal() {
  const isOpen = useModalStore(s => s.sellMobile.isOpen);
  const isEdit = useModalStore(s => s.sellMobile.isEdit);
  const record = useModalStore(s => s.sellMobile.record);

  const bump = useModalStore(s => s.bumpMobilesVersion);
  const close = useModalStore(s => s.sellMobile.close);

  const [form] = Form.useForm<FormValues>();
  const [loading, setLoading] = useState(false);

  // --- Khách hàng: search + nhận diện khớp tên
  const [optionsLoading, setOptionsLoading] = useState(false);
  const [suggestions, setSuggestions] = useState<CustomerRow[]>([]);
  const [isExistingCustomer, setIsExistingCustomer] = useState(false);
  const searchTimer = useRef<number | null>(null);
  const lastQuery = useRef<string>('');

  // Warranty options
  const optionsWarranty = useMemo(
    () => Array.from({ length: 13 }, (_, i) => ({ value: i, label: i === 0 ? 'Không bảo hành' : `${i} tháng` })),
    []
  );

  // initialValues (hỗ trợ cả dữ liệu cũ và export_*)
  const initialValues: Partial<FormValues> = useMemo(() => {
    const exportDate =
      parseDate(record?.export_date) ??
      parseDate(record?.sale_date) ??  // fallback nếu dữ liệu cũ
      dayjs();

    return {
      customer_name: record?.customer_name ?? '',
      customer_id: record?.customer_id ? Number(record.customer_id) : null,
      phone_number: record?.phone_number ?? '',

      export_price: toNumber(record?.export_price ?? record?.price, 0),
      export_cost:  toNumber(record?.export_cost  ?? record?.cost, 0),
      debt:         toNumber(record?.debt, 0),
      payment: String(record?.payment ?? '0') as Payment,
      export_date: exportDate,
      warranty: toNumber(record?.warranty ?? record?.export_warranty, 6),
      note: record?.note ?? '',
    };
  }, [isEdit, record]);

  // set flag khách cũ theo initial
  useEffect(() => {
    setIsExistingCustomer(!!initialValues.customer_id);
  }, [initialValues.customer_id]);

  // --- fetch customers (debounce theo tên)
  const fetchCustomers = async (q: string) => {
    try {
      setOptionsLoading(true);
      const res = await api.get('/admin/customers', { params: { search: q, limit: 10 } });
      const rows: CustomerRow[] = (Array.isArray(res.data?.data) ? res.data.data : Array.isArray(res.data) ? res.data : []).map((c: any) => ({
        id: Number(c.id),
        name: c.name ?? c.fullname ?? c.customer_name ?? '',
        phone: c.phone ?? c.phone_number ?? '',
      }));
      setSuggestions(rows);

      // Nếu text hiện tại trùng exact -> lock SĐT
      const currentName = form.getFieldValue('customer_name')?.trim() || '';
      if (currentName) {
        const matched = rows.find(r => r.name.trim().toLowerCase() === currentName.toLowerCase());
        if (matched) {
          setIsExistingCustomer(true);
          form.setFieldsValue({
            customer_id: matched.id,
            phone_number: matched.phone ?? '',
          });
          return;
        }
      }
      setIsExistingCustomer(false);
      form.setFieldsValue({ customer_id: null });
    } catch (e) {
      console.error(e);
      setSuggestions([]);
    } finally {
      setOptionsLoading(false);
    }
  };

  const handleSearchName = (q: string) => {
    lastQuery.current = q;
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

  const handleSelectName = (_value: string, option: any) => {
    // option = { value: name, label: name, id, phone }
    setIsExistingCustomer(true);
    form.setFieldsValue({
      customer_name: option.value,
      customer_id: option.id ?? null,
      phone_number: option.phone ?? '',
    });
  };

  const handleChangeName = (val: string) => {
    const matched = suggestions.find(r => r.name.trim().toLowerCase() === val.trim().toLowerCase());
    if (matched) {
      setIsExistingCustomer(true);
      form.setFieldsValue({
        customer_id: matched.id,
        phone_number: matched.phone ?? '',
      });
    } else {
      setIsExistingCustomer(false);
      form.setFieldsValue({ customer_id: null });
      // không xoá phone_number để user có thể tiếp tục nhập
    }
  };

  const handleBlurName = () => {
    const val = (form.getFieldValue('customer_name') || '').trim();
    if (!val) {
      setIsExistingCustomer(false);
      form.setFieldsValue({ customer_id: null, phone_number: '' });
      return;
    }
    const matched = suggestions.find(r => r.name.trim().toLowerCase() === val.toLowerCase());
    if (matched) {
      setIsExistingCustomer(true);
      form.setFieldsValue({
        customer_id: matched.id,
        phone_number: matched.phone ?? '',
      });
    } else {
      setIsExistingCustomer(false);
      form.setFieldsValue({ customer_id: null });
    }
  };

  const handleSubmit = async (values: FormValues) => {
    try {
      setLoading(true);

      const payload = {
        mobile_in_id: record?.id ?? null,
        customer_id: values.customer_id ?? null,                         // nếu trùng sẽ có id
        customer_name: (values.customer_name || '').trim(),              // luôn gửi tên
        phone_number: values.phone_number?.trim() || null,               // nếu khách cũ vẫn có thể gửi kèm

        export_price: toNumber(values.export_price, 0),
        expense:  toNumber(values.expense, 0),
        debt:         toNumber(values.debt, 0),
        payment: Number(values.payment),
        export_date: toYMD(values.export_date),                          // 'YYYY-MM-DD'
        warranty: Number(values.warranty),
        note: values.note?.trim() || null,
      };
      console.log('Saved sell:', payload);

      if (isEdit && record?.mobile_out?.id) {
        await api.put(`/mobile-out/${record.mobile_out.id}`, payload);
      } else {
        await api.post('/mobile-out', payload);
      }
      
      message.success(isEdit ? 'Cập nhật bán hàng thành công' : 'Bán hàng thành công');
      bump();
      close();
    } catch (e: any) {
      console.error(e);
      message.error(e?.response?.data?.message || 'Lỗi khi lưu bán hàng');
    } finally {
      setLoading(false);
    }
  };

  const formKey = isEdit ? `sell-edit-${record?.id ?? 'new'}` : 'sell-create';

  return (
    <Modal
      title={isEdit ? 'Sửa bán' : 'Bán sản phẩm'}
      open={isOpen}
      onCancel={close}
      footer={null}
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
        {/* KHÁCH HÀNG */}
        <Form.Item
          label="Khách hàng"
          name="customer_name"
          rules={[{ required: true, message: 'Nhập tên khách hàng' }]}
          tooltip="Gõ để tìm khách cũ; nếu trùng tên sẽ tự đổ SĐT; nếu không, hãy nhập SĐT mới"
        >
          <AutoComplete
            placeholder="Nhập tên khách hàng"
            onSearch={handleSearchName}
            onSelect={handleSelectName}
            onChange={handleChangeName}
            onBlur={handleBlurName}
            options={
              suggestions.map(s => ({
                value: s.name,
                label: s.name,
                id: s.id,
                phone: s.phone,
              })) as any[]
            }
            // có thể thêm notFoundContent={optionsLoading ? <Spin size="small" /> : null}
          />
        </Form.Item>

        {/* customer_id ẩn để submit nếu có */}
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

        {/* NGÀY, GIÁ, CHI PHÍ DẠNG EXPORT_* */}
        <Form.Item
          label="Ngày bán"
          name="export_date"
          rules={[{ required: true, message: 'Vui lòng nhập ngày bán' }]}
        >
          <DatePicker format="DD/MM/YYYY" style={{ width: '100%' }} placeholder="Chọn ngày" />
        </Form.Item>

        <Form.Item
          label="Giá bán"
          name="export_price"
          rules={[{ required: true, message: 'Vui lòng nhập giá bán' }]}
        >
          <InputNumber
            min={0}
            style={{ width: '100%' }}
            formatter={(v) => String(v ?? '').replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
            parser={parseNumber}
          />
        </Form.Item>

        <Form.Item label="Chi phí bán" name="expense">
          <InputNumber
            min={0}
            style={{ width: '100%' }}
            formatter={(v) => String(v ?? '').replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
            parser={parseNumber}
          />
        </Form.Item>

        <Form.Item label="Nợ lại" name="debt">
          <InputNumber
            min={0}
            style={{ width: '100%' }}
            formatter={(v) => String(v ?? '').replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
            parser={parseNumber}
          />
        </Form.Item>

        <Form.Item label="Bảo hành" name="warranty">
          <Select options={optionsWarranty} />
        </Form.Item>

        <Form.Item
          label="Thanh toán"
          name="payment"
          rules={[{ required: true, message: 'Chọn hình thức thanh toán' }]}
        >
          <Radio.Group>
            <Radio value="2">Tiền mặt</Radio>
            <Radio value="0">Chuyển khoản</Radio>
            <Radio value="1">Trả góp</Radio>
          </Radio.Group>
        </Form.Item>

        <Form.Item label="Ghi chú" name="note">
          <Input.TextArea rows={3} />
        </Form.Item>

        <Form.Item style={{ display: 'flex', justifyContent: 'center', marginTop: 16 }}>
          <Button type="primary" htmlType="submit" loading={loading}>
            {isEdit ? 'Lưu thay đổi' : 'Bán sản phẩm'}
          </Button>
        </Form.Item>
      </Form>
    </Modal>
  );
}
