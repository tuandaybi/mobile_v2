import { Modal, Form, Input, DatePicker, Select, Button, message, InputNumber } from 'antd';
import { useEffect, useMemo, useRef, useState, useCallback } from 'react';
import dayjs, { Dayjs } from 'dayjs';
import { useModalStore } from '@/store/modalStore'; // đường dẫn đúng của bạn
import api from '../../../axiosConfig';

interface FormValues {
  device_id?: number;
  color_id?: number;
  storage_id?: number;
  imei: string;
  battery: string;
  country_code: string;
  import_source: string;
  import_price: number;
  import_date: Dayjs;
  note: string;
}
interface Device { id:number; name:string }
interface Color { id:number; en_name?:string; vi_name?:string; name?:string }
interface Storage { id:number; name?:string; size_gb?:number }

const toId = (v:any) => (v===null||v===undefined||v==='') ? undefined : Number(v);

export default function MobileModal() {
  // ✅ lấy từng field bằng selector để đảm bảo re-render chính xác
  const isOpen  = useModalStore(s => s.mobile.isOpen);
  const isEdit  = useModalStore(s => s.mobile.isEdit);
  const record  = useModalStore(s => s.mobile.record);
  const close   = useModalStore(s => s.mobile.close);

  const [devices, setDevices]   = useState<Device[]>([]);
  const [colors, setColors]     = useState<Color[]>([]);
  const [storages, setStorages] = useState<Storage[]>([]);
  const [optionsLoading, setOptionsLoading] = useState(false);
  const [submitLoading, setSubmitLoading]   = useState(false);

  const toArray = (res:any) =>
    Array.isArray(res?.data) ? res.data :
    (Array.isArray(res?.data?.data) ? res.data.data : []);

  const fetchOptions = useCallback(async () => {
    setOptionsLoading(true);
    try {
      const [cs, ss, ds] = await Promise.all([
        api.get('/admin/colors'),
        api.get('/admin/storages'),
        api.get('/admin/devices?active=1'),
      ]);
      setColors(toArray(cs));
      setStorages(toArray(ss));
      setDevices(toArray(ds));
    } catch (e) {
      console.error(e);
      message.error('Lỗi khi lấy dữ liệu tuỳ chọn.');
    } finally {
      setOptionsLoading(false);
    }
  }, []);

  // fetch options mỗi lần MỞ modal
  const fetchedThisOpenRef = useRef(false);
  useEffect(() => {
    if (isOpen && !fetchedThisOpenRef.current) {
      fetchedThisOpenRef.current = true;
      fetchOptions();
    }
    if (!isOpen) fetchedThisOpenRef.current = false;
  }, [isOpen, fetchOptions]);

  // options for Select (value luôn là number)
  const optionsDevice  = useMemo(() => devices.map(d => ({ label:d.name, value:Number(d.id) })), [devices]);
  const optionsColor   = useMemo(() => colors.map(c => {
    const en=c.en_name?.trim(), vi=c.vi_name?.trim();
    const label = en && vi ? `${en} (${vi})` : (en || vi || c.name || `#${c.id}`);
    return { label, value:Number(c.id) };
  }), [colors]);
  const optionsStorage = useMemo(() => storages.map(s => ({
    label: s.name ?? `${s.size_gb} GB`, value:Number(s.id)
  })), [storages]);

  // ✅ initialValues tính từ record hiện tại (ưu tiên raw, fallback nested)
  const initialValues: Partial<FormValues> = useMemo(() => {
    if (isEdit && record) {
      return {
        device_id:  toId(record.device_id  ?? record.id_device  ?? record.device?.id),
        color_id:   toId(record.color_id   ?? record.id_color   ?? record.color?.id),
        storage_id: toId(record.storage_id ?? record.id_storage ?? record.storage?.id),
        imei: String(record.imei ?? ''),
        battery: String(
          record.battery_capacity ??
          (typeof record.battery === 'string' ? record.battery.replace(/\D/g,'') : '')
        ),
        country_code:  record.country_code ?? '',
        import_source: record.supplier ?? record.import_source ?? 'Khách lẻ',
        import_price:  Number(record.import_price ?? 0),
        import_date:   record.import_date ? dayjs(record.import_date) : dayjs(),
        note:          record.import_note ?? record.note ?? '',
      };
    }
    return {
      import_source: 'Khách lẻ',
      import_price: 0,
      import_date: dayjs(),
    };
  }, [isEdit, record]);

  const handleFinish = async (values: FormValues) => {
    try {
      setSubmitLoading(true);
      const payload = {
        device_id: values.device_id,
        color_id:  values.color_id,
        storage_id: values.storage_id,
        imei: values.imei.trim(),
        battery_capacity: values.battery ? Number(String(values.battery).replace(/\D/g,'')) : null,
        country_code: values.country_code?.trim() || null,
        supplier: values.import_source?.trim() || null,
        import_price: values.import_price ?? 0,
        import_date: values.import_date ? dayjs(values.import_date).format('YYYY-MM-DD') : null,
        import_note: values.note?.trim() || null,
      };
      if (isEdit && record?.id) {
        await api.put(`/mobile-in/${record.id}`, payload);
        message.success('Cập nhật thiết bị thành công');
      } else {
        await api.post('/mobile-in', payload);
        message.success('Thêm thiết bị thành công');
      }
      useModalStore.getState().bumpMobilesVersion();
      close();
    } catch (e:any) {
      console.error(e?.response?.data?.message);
      message.error(e?.response?.data?.message || 'Lỗi khi lưu thiết bị.');
    } finally {
      setSubmitLoading(false);
    }
  };

  return (
    <Modal
      title={isEdit ? 'Cập nhật thiết bị' : 'Thêm thiết bị'}
      open={isOpen}
      onCancel={close}
      footer={null}
      destroyOnHidden  // dùng theo cảnh báo antd
      // KHÔNG forceRender để Modal unmount khi đóng
    >
      {/* 👇 key này ép remount form mỗi lần mở/switch bản ghi */}
      <Form<FormValues>
        key={isEdit ? `edit-${record?.id ?? 'new'}` : 'create'}
        layout="horizontal"
        labelCol={{ span: 6 }}
        wrapperCol={{ span: 18 }}
        onFinish={handleFinish}
        initialValues={initialValues}
      >
        <Form.Item label="Thiết bị" name="device_id" rules={[{ required: true, message: 'Chọn thiết bị' }]}>
          <Select placeholder="Chọn thiết bị" options={optionsDevice} loading={optionsLoading} allowClear showSearch optionFilterProp="label" />
        </Form.Item>

        <Form.Item label="Imei hoặc Seri" name="imei" rules={[{ required: true, message: 'Vui lòng nhập Imei hoặc Seri' }, { min: 4, message: 'Tối thiểu 4 ký tự' }]}>
          <Input placeholder="VD: 352852115258376" />
        </Form.Item>

        <Form.Item label="Màu sắc" name="color_id" rules={[{ required: true, message: 'Chọn màu sắc' }]}>
          <Select placeholder="Chọn màu" options={optionsColor} loading={optionsLoading} allowClear showSearch optionFilterProp="label" />
        </Form.Item>

        <Form.Item label="Dung lượng" name="storage_id" rules={[{ required: true, message: 'Chọn dung lượng' }]}>
          <Select placeholder="Chọn dung lượng" options={optionsStorage} loading={optionsLoading} allowClear showSearch optionFilterProp="label" />
        </Form.Item>

        <Form.Item label="Pin" name="battery" rules={[{ required: true, message: 'Vui lòng nhập dung lượng pin (ví dụ 92%)' }]}>
          <InputNumber placeholder="Ví dụ: 92" style={{ width:'100%' }} />
        </Form.Item>

        <Form.Item label="Mã quốc gia" name="country_code" rules={[{ required: true, message: 'Vui lòng nhập mã quốc gia' }]}>
          <Input placeholder="Ví dụ: VN/A, LL/A..." />
        </Form.Item>

        <Form.Item label="Nguồn nhập" name="import_source" rules={[{ required: true, message: 'Vui lòng nhập nguồn nhập' }]}>
          <Input placeholder="Khách lẻ / Chợ / Nhà cung cấp..." />
        </Form.Item>

        <Form.Item label="Giá nhập" name="import_price" rules={[{ required: true, message: 'Vui lòng nhập giá nhập' }]}>
          <InputNumber min={0} style={{ width:'100%' }}
            formatter={(v)=>String(v ?? '').replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
            parser={((v?:string)=>{ const s=String(v ?? '').replace(/,/g,''); return s?Number(s):undefined; }) as any}
          />
        </Form.Item>

        <Form.Item label="Ngày nhập" name="import_date" rules={[{ required: true, message: 'Vui lòng chọn ngày nhập' }]}>
          <DatePicker format="DD/MM/YYYY" style={{ width:'100%' }} placeholder="Chọn ngày" />
        </Form.Item>

        <Form.Item label="Ghi chú" name="note">
          <Input.TextArea rows={3} placeholder="Ghi chú thêm (nếu có)" />
        </Form.Item>

        <Form.Item style={{ display:'flex', justifyContent:'center', marginTop:16 }}>
          <Button type="primary" htmlType="submit" loading={submitLoading}>
            {isEdit ? 'Lưu thay đổi' : 'Thêm mới'}
          </Button>
        </Form.Item>
      </Form>
    </Modal>
  );
}
