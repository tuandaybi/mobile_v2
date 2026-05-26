import { Modal, Form, Input, Select, DatePicker, Button, InputNumber, message } from 'antd';
import { useEffect, useMemo, useState } from 'react';
import dayjs, { Dayjs } from 'dayjs';
import customParseFormat from 'dayjs/plugin/customParseFormat';
import { useModalStore } from '../../store/modalStore';
import api from '../../../axiosConfig';

dayjs.extend(customParseFormat);

type ExpenseCategory = 'fixed' | 'inventory' | 'other';

interface FormValues {
  category: ExpenseCategory;
  name: string;
  amount: number;
  date: Dayjs;
  note?: string;
}

const parseNumber = ((v?: string) => {
  const s = String(v ?? '').replace(/,/g, '');
  return s ? Number(s) : undefined;
}) as any;

const parseDate = (v?: unknown): Dayjs => {
  if (!v) return dayjs();
  if (dayjs.isDayjs(v)) return v as Dayjs;
  const s = String(v).trim();
  const formats = ['YYYY-MM-DD', 'YYYY/MM/DD', 'YYYY-MM-DD HH:mm:ss', 'DD/MM/YYYY', 'DD-MM-YYYY'];
  for (const f of formats) {
    const d = dayjs(s, f, true);
    if (d.isValid()) return d;
  }
  const loose = dayjs(s);
  return loose.isValid() ? loose : dayjs();
};

const toYMD = (v?: Dayjs | string | null) =>
  v ? (dayjs.isDayjs(v) ? v.format('YYYY-MM-DD') : dayjs(v).format('YYYY-MM-DD')) : null;

const CATEGORY_OPTIONS = [
  { value: 'fixed', label: 'Chi phí cố định' },
  { value: 'inventory', label: 'Nhập hàng' },
  { value: 'other', label: 'Khác' },
];

export default function ExpenseModal() {
  const modal = useModalStore(s => s.expense);
  const bumpExpensesVersion = useModalStore(s => s.bumpExpensesVersion);

  const [form] = Form.useForm<FormValues>();
  const [loading, setLoading] = useState(false);

  const initialValues: Partial<FormValues> = useMemo(() => {
    const r: any = modal.record || {};
    return {
      category: (r.category as ExpenseCategory) ?? 'fixed',
      name: r.name ?? '',
      amount: Number(r.amount ?? 0),
      date: parseDate(r.date ?? ''),
      note: r.note ?? '',
    };
  }, [modal.record, modal.isEdit]);

  useEffect(() => {
    if (!modal.isOpen) return;
    form.resetFields();
    form.setFieldsValue(initialValues as any);
  }, [modal.isOpen, modal.record?.id]); // eslint-disable-line react-hooks/exhaustive-deps

  const handleSubmit = async (values: FormValues) => {
    setLoading(true);
    try {
      const payload = {
        category: values.category,
        name: values.name.trim(),
        amount: Number(values.amount ?? 0),
        date: toYMD(values.date),
        note: values.note?.trim() || null,
      };

      if (modal.isEdit && modal.record?.id) {
        await api.put(`/expenses/${modal.record.id}`, payload);
        message.success('Cập nhật chi phí thành công');
      } else {
        await api.post('/expenses', payload);
        message.success('Thêm chi phí thành công');
      }
      bumpExpensesVersion();
      modal.close?.();
    } catch (e: any) {
      console.error(e);
      message.error(e?.response?.data?.message || 'Lỗi khi lưu chi phí');
    } finally {
      setLoading(false);
    }
  };

  const formKey = modal.isEdit ? `expense-edit-${modal.record?.id ?? 'none'}` : 'expense-create';

  return (
    <Modal
      title={modal.isEdit ? 'Sửa chi phí' : 'Thêm khoản chi phí'}
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
          label="Loại chi phí"
          name="category"
          rules={[{ required: true, message: 'Chọn loại chi phí' }]}
        >
          <Select options={CATEGORY_OPTIONS} />
        </Form.Item>

        <Form.Item
          label="Tên chi phí"
          name="name"
          rules={[{ required: true, message: 'Vui lòng nhập tên chi phí' }]}
        >
          <Input placeholder="VD: Tiền điện tháng 5, Lương nhân viên..." />
        </Form.Item>

        <Form.Item
          label="Số tiền"
          name="amount"
          rules={[{ required: true, message: 'Vui lòng nhập số tiền' }]}
        >
          <InputNumber
            min={0}
            style={{ width: '100%' }}
            formatter={(v) => String(v ?? '').replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
            parser={parseNumber}
          />
        </Form.Item>

        <Form.Item
          label="Ngày chi"
          name="date"
          rules={[{ required: true, message: 'Chọn ngày chi' }]}
        >
          <DatePicker format="DD/MM/YYYY" style={{ width: '100%' }} placeholder="Chọn ngày" />
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
