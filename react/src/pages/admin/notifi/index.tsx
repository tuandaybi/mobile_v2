import { useEffect, useMemo, useState } from 'react';
import MainLayout from '../../../components/layout/MainLayout';
import {
  Card,
  Form,
  Input,
  Select,
  Button,
  Space,
  Radio,
  Switch,
  Typography,
  message,
} from 'antd';
import { useNavigate } from 'react-router-dom';
import api from '../../../../axiosConfig';

type StoreRow = {
  id: number;
  name: string;
  users?: Array<{ id: number; name: string; email: string; pivot?: any }>;
};

type UserRow = { id: number; name: string; email: string; store_ids: number[] };

type FormValues = {
  type: string;
  priority?: 'low' | 'normal' | 'high';
  title?: string;
  body: string;
  store_id?: number | null;
  ref_type?: string;
  send_to_all?: boolean;
  recipient_ids?: number[];
};

const TYPE_OPTIONS = [
  { label: 'Chung', value: 'log' },
  { label: 'Nhắc nhở', value: 'reminder' },
  { label: 'Hẹn gọi', value: 'call' },
];

const PRIORITY_OPTIONS = [
  { label: 'Thấp', value: 'low' },
  { label: 'Thường', value: 'normal' },
  { label: 'Cao', value: 'high' },
];

export default function CreateNotificationPage() {
  const [form] = Form.useForm<FormValues>();
  const nav = useNavigate();

  const [loading, setLoading] = useState(false);
  const [stores, setStores] = useState<StoreRow[]>([]);
  const [users, setUsers] = useState<UserRow[]>([]);

  const storeId = Form.useWatch('store_id', form);

  // nạp danh sách store + user (từ stores.users)
  useEffect(() => {
    (async () => {
      try {
        const res = await api.get<{ stores: StoreRow[] }>('admin/stores', {
          suppressToast: true as any,
        });

        const storesResp = res.data.stores || [];
        setStores(storesResp);

        // --- normalize users: gộp users từ tất cả store, cộng dồn store_ids ---
        const userMap = new Map<number, UserRow>();
        for (const st of storesResp) {
          const sid = Number(st.id);
          const sUsers = st.users || [];
          for (const u of sUsers) {
            const uid = Number(u.id);
            if (!userMap.has(uid)) {
              userMap.set(uid, {
                id: uid,
                name: String(u.name),
                email: String(u.email ?? ''),
                store_ids: [sid],
              });
            } else {
              const cur = userMap.get(uid)!;
              if (!cur.store_ids.includes(sid)) cur.store_ids.push(sid);
            }
          }
        }

        setUsers(Array.from(userMap.values()));
      } catch (e) {
        console.error('Fetch stores/users failed', e);
      }
    })();
  }, []);

  // filter user theo store_id đang chọn
  const filteredUsers = useMemo(() => {
    if (!storeId) return users; // không chọn -> hiện tất cả
    const sid = Number(storeId);
    return users.filter((u) => u.store_ids.includes(sid));
  }, [users, storeId]);

  const onSubmit = async (values: FormValues, goBack = false) => {
    const payload: FormValues = {
      type: values.type,
      priority: values.priority ?? 'normal',
      title: values.title?.trim() || undefined,
      body: values.body.trim(),
      store_id: values.store_id || undefined,
      send_to_all: values.send_to_all ?? false,
      ref_type: 'admin',
      recipient_ids: values.send_to_all
        ? undefined
        : values.recipient_ids ?? [],
    };

    if (
      !payload.send_to_all &&
      (!payload.recipient_ids || payload.recipient_ids.length === 0)
    ) {
      message.warning('Chọn người nhận hoặc bật “Gửi tất cả”.');
      return;
    }
    console.log('Submit payload', payload);
    setLoading(true);
    try {
      await api.post('/inbox', payload);
      message.success('Đã tạo thông báo!');
      if (goBack) {
        nav(-1);
      } else {
        form.resetFields();
      }
    } catch (e: any) {
      const msg = e?.response?.data?.message ?? 'Tạo thông báo thất bại';
      message.error(msg);
    } finally {
      setLoading(false);
    }
  };

  return (
    <MainLayout>
      <Card
        title="Tạo thông báo"
        style={{ maxWidth: '100%', margin: '0 auto' }}
      >
        <Form<FormValues>
          form={form}
          layout="vertical"
          initialValues={{
            type: 'log',
            priority: 'normal',
            send_to_all: true,
            store_id: undefined,
            recipient_ids: [],
          }}
          onFinish={(v) => onSubmit(v, false)}
        >
          <Form.Item
            label="Loại"
            name="type"
            rules={[{ required: true, message: 'Chọn loại thông báo' }]}
          >
            <Radio.Group
              options={TYPE_OPTIONS}
              optionType="button"
              buttonStyle="solid"
            />
          </Form.Item>

          <Space size="large" style={{ width: '100%' }} wrap>
            <Form.Item
              label="Mức ưu tiên"
              name="priority"
              style={{ minWidth: 220 }}
            >
              <Select options={PRIORITY_OPTIONS} />
            </Form.Item>

            <Form.Item
              label="Cửa hàng (tuỳ chọn)"
              name="store_id"
              style={{ minWidth: 280 }}
            >
              <Select
                allowClear
                showSearch
                optionFilterProp="label"
                options={stores.map((s) => ({ label: `${s.name} (${s.id})`, value: s.id }))}
                placeholder="Không chọn = toàn hệ thống"
                onChange={() => {
                  const cur = form.getFieldValue(
                    'recipient_ids'
                  ) as number[] | undefined;
                  if (!cur?.length) return;

                  const sid = form.getFieldValue('store_id');
                  const sidNum = sid ? Number(sid) : undefined;

                  if (sidNum == null) return;

                  const keep = (cur ?? []).filter((id) => {
                    const u = users.find((x) => x.id === id);
                    return !!u && u.store_ids.includes(sidNum);
                  });

                  form.setFieldsValue({ recipient_ids: keep });
                }}
              />
            </Form.Item>
          </Space>

          <Form.Item label="Tiêu đề" name="title">
            <Input maxLength={120} showCount placeholder="(Tuỳ chọn)" />
          </Form.Item>

          <Form.Item
            label={
              <Space>
                <span>Nội dung</span>
                <Typography.Text type="secondary">
                  (bắt buộc)
                </Typography.Text>
              </Space>
            }
            name="body"
            rules={[{ required: true, message: 'Nhập nội dung' }]}
          >
            <Input.TextArea
              rows={10}
              maxLength={5000}
              showCount
              placeholder="Nhập nội dung thông báo..."
            />
          </Form.Item>

          <Space align="start" style={{ width: '100%' }} wrap>
            <Form.Item
              label="Gửi tất cả"
              name="send_to_all"
              valuePropName="checked"
              tooltip="Nếu bật: backend sẽ gửi cho toàn bộ người dùng (hoặc toàn bộ người dùng thuộc cửa hàng đã chọn)."
            >
              <Switch />
            </Form.Item>

            <Form.Item
              noStyle
              shouldUpdate={(prev, cur) =>
                prev.send_to_all !== cur.send_to_all ||
                prev.store_id !== cur.store_id
              }
            >
              {({ getFieldValue }) => {
                const sendAll = !!getFieldValue('send_to_all');
                return (
                  <Form.Item
                    label="Người nhận (nếu không gửi tất cả)"
                    name="recipient_ids"
                    style={{ minWidth: 520 }}
                    rules={
                      sendAll
                        ? []
                        : [
                            {
                              required: true,
                              message:
                                'Chọn người nhận hoặc bật “Gửi tất cả”.',
                            },
                          ]
                    }
                  >
                    <Select
                      mode="multiple"
                      allowClear
                      disabled={sendAll}
                      showSearch
                      optionFilterProp="label"
                      placeholder={
                        sendAll
                          ? 'Đang chọn: Gửi tất cả'
                          : 'Chọn người nhận...'
                      }
                      options={filteredUsers.map((u) => ({
                        value: u.id,
                        label: `${u.name} (${
                          u.store_ids.length
                            ? `${u.store_ids.join(',')}`
                            : 'không có cửa hàng'
                        })`,
                      }))}
                      maxTagCount="responsive"
                    />
                  </Form.Item>
                );
              }}
            </Form.Item>
          </Space>

          <Form.Item>
            <Space>
              <Button
                type="primary"
                htmlType="submit"
                loading={loading}
              >
                Gửi thông báo
              </Button>
            </Space>
          </Form.Item>
        </Form>
      </Card>
    </MainLayout>
  );
}
