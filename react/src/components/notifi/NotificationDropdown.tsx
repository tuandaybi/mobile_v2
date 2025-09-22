// src/components/NotificationDropdown.tsx
import { useEffect, useMemo, useState } from 'react';
import {
  Badge, Button, Card, Divider, List, Popover, Tabs, Tag, Typography, Space, Switch, Empty
} from 'antd';
import { BellOutlined, CheckOutlined, DeleteOutlined } from '@ant-design/icons';
import api from '../../../axiosConfig';
import dayjs from 'dayjs';
import 'dayjs/locale/vi';
import relativeTime from 'dayjs/plugin/relativeTime';

dayjs.extend(relativeTime);
dayjs.locale('vi');

type Noti = {
  id: number;
  type: 'log' | 'reminder' | 'call' | string;
  title?: string | null;
  body: string;
  priority?: 'low'|'normal'|'high';
  created_at: string;
  read_at?: string | null;
  creator?: { id:number; name:string };
};

type InboxResp = {
  items: Noti[];
  meta: { total:number; current_page:number; per_page:number; unread_count:number };
};

// 3 loại rõ ràng, general = log
const TABS = [
  { key: 'general', label: 'Chung',    type: 'log' as const },
  { key: 'reminder',label: 'Nhắc nhở', type: 'reminder' as const },
  { key: 'call',    label: 'Hẹn gọi',  type: 'call' as const },
];

const PRIORITY_COLOR: Record<string, string> = {
  high: 'red', normal: 'blue', low: 'default'
};

export default function NotificationDropdown() {
  const [open, setOpen] = useState(false);
  const [activeKey, setActiveKey] = useState<string>('general');

  const [data, setData] = useState<Record<string, Noti[]>>({
    general: [], reminder: [], call: []
  });
  const [page, setPage] = useState<Record<string, number>>({
    general: 1, reminder: 1, call: 1
  });
  const [total, setTotal] = useState<Record<string, number>>({
    general: 0, reminder: 0, call: 0
  });

  const [loading, setLoading] = useState(false);
  const [unread, setUnread]   = useState(0); // tổng chưa đọc toàn hệ thống
  const [onlyUnread, setOnlyUnread] = useState(false);

  // số chưa đọc theo từng tab
  const [unreadByTab, setUnreadByTab] = useState<Record<string, number>>({
    general: 0, reminder: 0, call: 0
  });

  const perPage = 15;

  // ----- API helpers -----
  const fetchTab = async (tabKey: string, reset = false) => {
    const tab = TABS.find(t => t.key === tabKey)!;
    const nextPage = reset ? 1 : ((page[tabKey] ?? 1) + 1);

    const params: any = {
      perPage,
      page: nextPage,
      q: '',
      unread: onlyUnread ? 1 : undefined,
      type: tab.type,
    };

    const res = await api.get<InboxResp>('/inbox', { params, suppressToast: true as any });
    const items = res.data.items || [];

    setData(prev => ({
      ...prev,
      [tabKey]: reset ? items : [...(prev[tabKey] || []), ...items],
    }));
    setTotal(prev => ({ ...prev, [tabKey]: res.data.meta?.total || 0 }));
    setUnread(res.data.meta?.unread_count || 0);
    setPage(prev => ({ ...prev, [tabKey]: nextPage }));
  };
  // đếm unread cho 1 tab (nhẹ, chỉ lấy meta.total với unread=1)
  const fetchUnreadForTab = async (tabKey: string) => {
    const tab = TABS.find(t => t.key === tabKey)!;
    const res = await api.get<InboxResp>('/inbox', {
      params: { perPage: 1, unread: 1, type: tab.type },
      suppressToast: true as any,
    });
    const cnt = res.data.meta?.total || 0;
    setUnreadByTab(prev => ({ ...prev, [tabKey]: cnt }));
  };

  // đếm unread tổng
  const fetchUnreadCount = async () => {
    try {
      const res = await api.get<InboxResp>('/inbox', {
        params: { perPage: 1 },
        suppressToast: true as any,
      });
      setUnread(res.data.meta?.unread_count || 0);
    } catch {}
  };

  // ----- Effects -----

  // Khi mở popover: chỉ tải log (general) + lấy badge unread cho cả 3 tab
  useEffect(() => {
    if (!open) return;
    // reset page riêng cho từng tab
    setPage({ general:1, reminder:1, call:1 });
    setLoading(true);
    (async () => {
      try {
        await Promise.all(TABS.map(t => fetchUnreadForTab(t.key)));
        await fetchTab('general', true); // chỉ load log lúc mở
      } finally {
        setLoading(false);
      }
    })();
  }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

  // Đổi tab: nếu tab chưa có dữ liệu thì mới tải tab đó
  useEffect(() => {
    if (!open) return;
    setPage(p => ({ ...p, [activeKey]: 1 }));
    fetchTab(activeKey, true);
    fetchUnreadForTab(activeKey);
  }, [activeKey]);

  // Bật/tắt "Chỉ chưa đọc": refetch lại tab đang xem (không tải tab khác)
  useEffect(() => {
    if (!open) return;
    setPage(p => ({ ...p, [activeKey]: 1 }));
    fetchTab(activeKey, true);
  }, [onlyUnread]); // eslint-disable-line react-hooks/exhaustive-deps

  // Poll badge tổng chưa đọc
  useEffect(() => {
    fetchUnreadCount();
    const t = setInterval(fetchUnreadCount, 15000);
    return () => clearInterval(t);
  }, []);

  // ----- Derived -----
  const hasMore = useMemo(() => {
    const totalForTab = total[activeKey] || 0;
    const loaded = data[activeKey]?.length || 0;
    return loaded < totalForTab;
  }, [data, total, activeKey]);

  // ----- Actions -----
  const loadMore = async () => {
    await fetchTab(activeKey);
  };

  const markRead = async (id: number) => {
    await api.post(`/inbox/${id}/read`, null, { suppressToast: true as any });

    // cập nhật list
    setData(prev => {
      if (onlyUnread) {
        const nextArr = (prev[activeKey] || []).filter(x => x.id !== id);
        return { ...prev, [activeKey]: nextArr };
      }
      const arr = prev[activeKey].map(x => x.id === id ? { ...x, read_at: new Date().toISOString() } : x);
      return { ...prev, [activeKey]: arr };
    });

    // cập nhật badge tổng & badge tab
    setUnread(u => Math.max(0, u - 1));
    setUnreadByTab(prev => ({ ...prev, [activeKey]: Math.max(0, (prev[activeKey] || 0) - 1) }));

    // nếu đang xem onlyUnread, giảm total của tab hiện tại cho khớp nút "Xem thêm"
    if (onlyUnread) {
      setTotal(prev => ({ ...prev, [activeKey]: Math.max(0, (prev[activeKey] || 0) - 1) }));
    }
  };

  const markAllRead = async () => {
    try {
      await api.post('/inbox/read-all', null, { suppressToast: true as any });

      // set read_at cho mọi tab đã tải ở client
      setData(prev => {
        const copy: Record<string, Noti[]> = { ...prev };
        Object.keys(copy).forEach(k => {
          copy[k] = copy[k].map(x => ({ ...x, read_at: x.read_at ?? new Date().toISOString() }));
        });
        return copy;
      });

      // reset badge
      setUnread(0);
      setUnreadByTab({ general: 0, reminder: 0, call: 0 });

      // nếu đang onlyUnread thì clear list/tổng tab đang hiển thị
      if (onlyUnread) {
        setTotal({ general: 0, reminder: 0, call: 0 });
        setData({ general: [], reminder: [], call: [] });
      }
    } catch {
      // fallback: chỉ tab hiện tại
      const ids = data[activeKey].filter(x => !x.read_at).map(x => x.id);
      await Promise.all(ids.map(id => api.post(`/inbox/${id}/read`, null, { suppressToast: true as any })));
      setData(prev => {
        const arr = prev[activeKey].map(x => ({ ...x, read_at: x.read_at ?? new Date().toISOString() }));
        return { ...prev, [activeKey]: arr };
      });
      setUnread(u => Math.max(0, u - ids.length));
      setUnreadByTab(prev => ({ ...prev, [activeKey]: Math.max(0, (prev[activeKey] || 0) - ids.length) }));
      if (onlyUnread) {
        setData(prev => ({ ...prev, [activeKey]: [] }));
        setTotal(prev => ({ ...prev, [activeKey]: 0 }));
      }
    }
  };
  const deleteRead = async () => {
    try {
      await api.delete('/inbox/read', { suppressToast: true as any });
      // xóa các mục đã đọc khỏi tất cả các tab đã tải
      setData(prev => {
        const copy: Record<string, Noti[]> = { ...prev };
        Object.keys(copy).forEach(k => {
          copy[k] = copy[k].filter(x => !x.read_at);
        });
        return copy;
      });
      // cập nhật total cho các tab đã tải
      setTotal(prev => {
        const copy: Record<string, number> = { ...prev };
        Object.keys(copy).forEach(k => {
          const readCount = (data[k] || []).filter(x => x.read_at).length;
          copy[k] = Math.max(0, (copy[k] || 0) - readCount);
        });
        return copy;
      });
    } catch (e) {
      console.error('Delete read notifications failed', e);
    }
  };

  // ----- UI -----
  const content = (
    <Card
      style={{ width: 560, boxShadow: '0 8px 24px rgba(0,0,0,0.12)', borderRadius: 14, padding: 0 }}
      variant="outlined"
    >
      <div style={{ display: 'flex', alignItems: 'center', padding: '14px 16px' }}>
        <Typography.Title level={5} style={{ margin: 0, flex: 1 }}>Thông báo</Typography.Title>
        <Space size="middle">
          <Space size={6}>
            <Typography.Text type="secondary">Chỉ chưa đọc</Typography.Text>
            <Switch size="small" checked={onlyUnread} onChange={setOnlyUnread} />
          </Space>
          <Button size="small" type="text" icon={<CheckOutlined />} onClick={markAllRead}>
            Đã đọc tất cả
          </Button>
          <Button size="small" type="text" icon={<DeleteOutlined />} onClick={deleteRead}>
            Xóa đã đọc
          </Button>
        </Space>
      </div>
      <Divider style={{ margin: 0 }} />
      <div style={{ padding: '0 12px' }}>
        <Tabs
          activeKey={activeKey}
          onChange={(k) => setActiveKey(k)}
          items={TABS.map(t => {
            const perTabUnread = unreadByTab[t.key] || 0;
            return {
              key: t.key,
              label: (
                <Space>
                  {t.label}
                  {/* badge đỏ: số chưa đọc của tab */}
                  <Badge count={perTabUnread} overflowCount={99} style={{ backgroundColor: '#ff4d4f' }} />
                </Space>
              ),
            };
          })}
        />
      </div>

      <div style={{ maxHeight: 420, overflow: 'auto', padding: '0 8px 8px' }}>
        <List
          locale={{ emptyText: <Empty description="Không có thông báo" image={Empty.PRESENTED_IMAGE_SIMPLE} /> }}
          dataSource={data[activeKey]}
          renderItem={(item) => {
            const isUnread = !item.read_at;
            const created = dayjs(item.created_at);
            return (
              <List.Item
                onClick={() => isUnread && markRead(item.id)}
                style={{
                  cursor: 'pointer',
                  padding: '12px 8px',
                  background: isUnread ? 'rgba(24,144,255,0.06)' : 'transparent',
                  borderRadius: 10,
                  margin: '6px 8px'
                }}
              >
                <Space align="start" size={12} style={{ width: '100%' }}>
                  <div style={{
                    width: 42, height: 42, borderRadius: 24, background: '#eef5ff',
                    display: 'flex', alignItems: 'center', justifyContent: 'center', position: 'relative'
                  }}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="#1677ff">
                      <path d="M3 4h18v2H3V4zm0 5h18v2H3V9zm0 5h12v2H3v-2z" />
                    </svg>
                    {isUnread && <span style={{
                      position: 'absolute', right: -1, top: -1, width: 9, height: 9,
                      borderRadius: 9, background: '#ff4d4f'
                    }} />}
                  </div>
                  <div style={{ flex: 1 }}>
                    <div style={{ fontWeight: 600 }}>{item.title || 'Thông báo'}</div>
                    <Typography.Paragraph style={{ margin: '2px 0 6px' }} ellipsis={{ rows: 2 }}>
                      {item.body}
                    </Typography.Paragraph>
                    <Space size={8} wrap>
                      {item.priority && <Tag color={PRIORITY_COLOR[item.priority] || 'default'}>{item.priority}</Tag>}
                      <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                        {created.format('HH:mm DD/MM')} • {created.fromNow()}
                      </Typography.Text>
                      {item.creator?.name && (
                        <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                          • {item.creator.name}
                        </Typography.Text>
                      )}
                    </Space>
                  </div>
                </Space>
              </List.Item>
            );
          }}
        />
        {hasMore && (
          <>
            <Divider style={{ margin: '6px 0' }} />
            <div style={{ textAlign: 'center', padding: '8px 0 12px' }}>
              <Button loading={loading} onClick={loadMore} size="small">
                Xem thêm
              </Button>
            </div>
          </>
        )}
      </div>
    </Card>
  );

  return (
    <Popover
      trigger="click"
      open={open}
      onOpenChange={setOpen}
      placement="bottomRight"
      styles={{ body: { padding: 0 } }}
      content={content}
    >
      <Badge count={unread} size="small" overflowCount={10}>
        <Button type="text" shape="circle" icon={<BellOutlined />} style={{ color: '#fff', fontSize: 18 }} />
      </Badge>
    </Popover>
  );
}
