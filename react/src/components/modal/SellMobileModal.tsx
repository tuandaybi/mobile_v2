import { Modal, Form, Input, DatePicker, Button, Radio, InputNumber, message, Select, AutoComplete } from 'antd';
import { useMemo, useState, useEffect, useRef } from 'react';
import dayjs, { Dayjs } from 'dayjs';
import customParseFormat from 'dayjs/plugin/customParseFormat';
import { useModalStore } from '../../store/modalStore';
import api from '../../../axiosConfig';

dayjs.extend(customParseFormat);

type Payment = '0' | '1' | '2'; // 0: chuyển khoản, 1: trả góp, 2: tiền mặt

interface FormValues {
  customer_name: string;
  customer_id?: number | null;
  phone_number?: string;

  export_price: number;
  expense?: number;
  debt?: number;
  payment: Payment;
  export_date: Dayjs;
  warranty: number;
  note?: string;
  mobile_in_id?: number | null;
}

type CustomerRow = { id: number; name: string; phone?: string };

const toNumber = (v: any, d = 0) => (v === undefined || v === null || v === '' ? d : Number(v));
const parseNumber = ((v?: string) => {
  const s = String(v ?? '').replace(/,/g, '');
  return s ? Number(s) : undefined;
}) as any;

const parseApiDateToDayjs = (s?: string | Dayjs | null) => {
  if (!s) return null;
  if (dayjs.isDayjs(s)) return s;
  // normalize "2025-08-25T12:02:50.000000Z" -> "...Z"
  const normalized = String(s).replace(/\.\d+Z$/, 'Z');
  const d = dayjs(normalized);
  return d.isValid() ? d : null;
};
const toYMD = (v?: Dayjs | string | null) =>
  v ? (dayjs.isDayjs(v) ? v.format('YYYY-MM-DD') : dayjs(v).format('YYYY-MM-DD')) : null;

export default function SellMobileModal() {
  const isOpen  = useModalStore(s => s.sellMobile.isOpen);
  const isEdit  = useModalStore(s => s.sellMobile.isEdit);
  const record  = useModalStore(s => s.sellMobile.record);
  const bump    = useModalStore(s => s.bumpMobilesVersion);
  const close   = useModalStore(s => s.sellMobile.close);
  const [opened, setOpened] = useState(false);

  const [mobileInId, setMobileInId] = useState<number | undefined>(undefined);

  const [form] = Form.useForm<FormValues>();
  const [loading, setLoading] = useState(false);

  // tìm khách hàng
  const [_, setOptionsLoading] = useState(false);
  const [suggestions, setSuggestions] = useState<CustomerRow[]>([]);
  const [isExistingCustomer, setIsExistingCustomer] = useState(false);
  const searchTimer = useRef<number | null>(null);
  const lastQuery = useRef<string>('');

  // Warranty options
  const optionsWarranty = useMemo(
    () => Array.from({ length: 13 }, (_, i) => ({ value: i, label: i === 0 ? 'Không bảo hành' : `${i} tháng` })),
    []
  );

  // Lấy id chuẩn để sửa: ưu tiên mobile_out_id
  const moId = useMemo<number | undefined>(() => {
    const id = record?.mobile_out_id ?? record?.mobile_out?.id ?? record?.id;
    return id ? Number(id) : undefined;
  }, [record]);

  // initialValues từ record có sẵn (khi tạo mới hoặc lần mở đầu)
  const initialValues: Partial<FormValues> = useMemo(() => {
    const exportDate =
      parseApiDateToDayjs((record as any)?.export_date) ??
      parseApiDateToDayjs((record as any)?.sale_date) ??
      parseApiDateToDayjs((record as any)?.date) ??
      dayjs();

    return {
      customer_name: (record as any)?.customer_name ?? '',
      customer_id: (record as any)?.customer_id ? Number((record as any)?.customer_id) : null,
      phone_number: (record as any)?.phone_number ?? (record as any)?.customer_phone ?? '',

      export_price: toNumber((record as any)?.export_price ?? (record as any)?.price, 0),
      expense:      toNumber((record as any)?.expense ?? (record as any)?.export_cost ?? (record as any)?.cost, 0),
      // chỉ set debt khi tạo mới (khi edit sẽ ẩn field này)
      debt:         !isEdit ? toNumber((record as any)?.debt, 0) : undefined,
      payment: String((record as any)?.payment ?? '0') as Payment,
      export_date: exportDate!,
      warranty: toNumber((record as any)?.warranty ?? (record as any)?.export_warranty, 6),
      note: (record as any)?.note ?? '',
    };
  }, [isEdit, record]);

  // set flag khách cũ theo initial
  useEffect(() => {
    setIsExistingCustomer(!!initialValues.customer_id);
  }, [initialValues.customer_id]);

  useEffect(() => {
    const init =
      (record as any)?.mobile_in_id
      ?? (record as any)?.mobileIn?.id
      ?? (!isEdit ? (record as any)?.id : undefined);
    setMobileInId(init ? Number(init) : undefined);
  }, [record, isEdit]);

  useEffect(() => {
    if (!opened || !isEdit || !moId) return;

    let alive = true;
    (async () => {
      try {
        setLoading(true);
        const res = await api.get(`/mobile-out/${moId}`); // ✅ đồng bộ prefix
        const d = res.data || {};
        if (!alive) return;

        const miId = d.mobile_in_id ?? d.mobile_in?.id ?? mobileInId ?? null;
        setMobileInId(miId ? Number(miId) : undefined);

        form.setFieldsValue({
          customer_name: d.customer_name ?? initialValues.customer_name ?? '',
          customer_id:   d.customer_id ?? initialValues.customer_id ?? null,
          phone_number:  d.customer_phone ?? initialValues.phone_number ?? '',
          export_price:  toNumber(d.price ?? d.subtotal ?? initialValues.export_price ?? 0),
          expense:       toNumber(d.expense ?? initialValues.expense ?? 0),
          payment:       String(d.payment ?? initialValues.payment ?? '0') as Payment,
          debt: 0,
          export_date:   parseApiDateToDayjs(d.date) ?? initialValues.export_date ?? dayjs(),
          warranty:      toNumber(d.warranty ?? initialValues.warranty ?? 6),
          note:          d.note ?? initialValues.note ?? '',
          mobile_in_id:  miId ?? undefined,
        });
      } finally {
        if (alive) setLoading(false);
      }
    })();

    return () => { alive = false; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [opened, isEdit, moId]);

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
      form.setFieldsValue({ customer_id: matched.id, phone_number: matched.phone ?? '' });
    } else {
      setIsExistingCustomer(false);
      form.setFieldsValue({ customer_id: null });
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
      form.setFieldsValue({ customer_id: matched.id, phone_number: matched.phone ?? '' });
    } else {
      setIsExistingCustomer(false);
      form.setFieldsValue({ customer_id: null });
    }
  };

  const handleSubmit = async (values: FormValues) => {
    try {
      setLoading(true);

      const payload: any = {
        // CHÚ Ý: xác định đúng các id
        mobile_out_id: moId ?? null,
        mobile_in_id: mobileInId ?? null,

        customer_id: values.customer_id ?? null,
        customer_name: (values.customer_name || '').trim(),
        phone_number: values.phone_number?.trim() || null,
        debt: values.debt ? toNumber(values.debt, 0) : 0,
        export_price: toNumber(values.export_price, 0),
        expense:      toNumber(values.expense, 0),
        payment:      Number(values.payment),
        export_date:  toYMD(values.export_date),
        warranty:     Number(values.warranty),
        note:         values.note?.trim() || null,
      };

      if (isEdit && moId) {
        await api.put(`/mobile-out/${moId}`, payload);
      } else {
        await api.post('/mobile-out', payload);
      }

      message.success(isEdit ? 'Cập nhật bán hàng thành công' : 'Bán hàng thành công');
      bump();
      form.resetFields();
      close();
    } catch (e: any) {
      console.error(e);
      message.error(e?.response?.data?.message || 'Lỗi khi lưu bán hàng');
    } finally {
      setLoading(false);
    }
  };

  // Form key theo mobile_out_id để ép remount khi đổi item
  const formKey = isEdit ? `sell-edit-${moId ?? 'new'}` : 'sell-create';

  return (
    <Modal
      title={isEdit ? 'Sửa bán' : 'Bán sản phẩm'}
      open={isOpen}
      onCancel={() => { form.resetFields(); close(); }}
      footer={null}
      loading={loading}
      afterOpenChange={(open) => setOpened(open)}
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

        <Form.Item name="mobile_in_id" hidden>
          <Input />
        </Form.Item>

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
            options={suggestions.map(s => ({
              value: s.name,
              label: s.name,
              id: s.id,
              phone: s.phone,
            })) as any[]}
          />
        </Form.Item>

        <Form.Item name="customer_id" hidden><Input /></Form.Item>

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

        <Form.Item label="Ngày bán" name="export_date" rules={[{ required: true, message: 'Vui lòng nhập ngày bán' }]}>
          <DatePicker format="DD/MM/YYYY" style={{ width: '100%' }} placeholder="Chọn ngày" />
        </Form.Item>

        <Form.Item label="Giá bán" name="export_price" rules={[{ required: true, message: 'Vui lòng nhập giá bán' }]}>
          <InputNumber min={0} style={{ width: '100%' }}
            formatter={(v) => String(v ?? '').replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
            parser={parseNumber}
          />
        </Form.Item>

        <Form.Item label="Chi phí bán" name="expense">
          <InputNumber min={0} style={{ width: '100%' }}
            formatter={(v) => String(v ?? '').replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
            parser={parseNumber}
          />
        </Form.Item>

        <Form.Item label="Nợ lại" name="debt">
          <InputNumber min={0} style={{ width: '100%' }}
            formatter={(v) => String(v ?? '').replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
            parser={parseNumber}
          />
        </Form.Item>

        <Form.Item label="Bảo hành" name="warranty">
          <Select options={optionsWarranty} />
        </Form.Item>

        <Form.Item label="Thanh toán" name="payment" rules={[{ required: true, message: 'Chọn hình thức thanh toán' }]}>
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
          <Button type="primary" htmlType="submit">
            {isEdit ? 'Lưu thay đổi' : 'Bán sản phẩm'}
          </Button>
        </Form.Item>
      </Form>
    </Modal>
  );
}
