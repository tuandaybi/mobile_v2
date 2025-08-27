import React from "react";
import { Modal, Descriptions, Table, Skeleton, Button, Empty } from "antd";
import dayjs from "dayjs";
import type { ColumnsType } from "antd/es/table";
import type { OriginType } from "./DebtDetailDrawer";

export type MobileOutItem = {
  imei?: string;
  device_name?: string;
  color?: string;
  storage?: string;
  price: number | string;
};
export type MobileOutDetail = {
  id: number;
  code?: string;
  customer_name?: string;
  items: MobileOutItem[];
  subtotal: number | string;
  paid: number | string;
  debt: number | string;
  date?: string;
  note?: string;
};
export type ServiceDetail = {
  id: number;
  service_name?: string;
  customer_name?: string;
  service_date?: string;
  service_price: number | string;
  expense?: number | string;
  paid: number | string;
  debt: number | string;
  warranty?: number;
  note?: string;
};

type Props = {
  open: boolean;
  loading: boolean;
  type: OriginType;
  id: number | null;
  data?: MobileOutDetail | ServiceDetail | null;

  debtId?: number;                 // debt đang xem
  debtRemaining?: number | string; // số còn lại để trả nhanh
  onClose: () => void;
  onPayRemaining?: (debtId: number, amount: number) => void;
};

const toNum = (v: any) => {
  if (v == null) return 0;
  const n = Number(String(v).replace(/,/g, ""));
  return Number.isFinite(n) ? n : 0;
};

const currency = (v: any) => `${toNum(v).toLocaleString()} đ`;

const fmtDate = (s?: string) => {
  if (!s) return '—';
  const m = dayjs(new Date(s)); // JS Date parse được microseconds
  return m.isValid() ? m.format('DD/MM/YYYY HH:mm') : '—';
};

const OriginDetailModal: React.FC<Props> = ({
  open, loading, type, id, data,
  debtId, debtRemaining, onClose, onPayRemaining
}) => {
  const title =
    type === 'mobile'
      ? `Chi tiết bán máy #${id}`
      : type === 'service'
      ? `Chi tiết dịch vụ #${id}`
      : 'Chi tiết nguồn phát sinh';

  return (
    <Modal
      open={open}
      title={title}
      onCancel={onClose}
      width={820}
      footer={[
        <Button key="close" onClick={onClose}>Đóng</Button>,
        type !== 'unknown' && debtId && onPayRemaining ? (
          <Button
            key="pay"
            type="primary"
            onClick={() => onPayRemaining(debtId, toNum(debtRemaining))}
          >
            Trả phần còn lại ({currency(debtRemaining)})
          </Button>
        ) : null,
      ]}
    >
      {loading ? (
        <Skeleton active paragraph={{ rows: 6 }} />
      ) : !data ? (
        <Empty description="Không có dữ liệu nguồn" />
      ) : type === 'mobile' ? (
        (() => {
          const d = data as MobileOutDetail;
          const cols: ColumnsType<MobileOutItem> = [
            { title: 'IMEI', dataIndex: 'imei' },
            { title: 'Thiết bị', dataIndex: 'device_name' },
            { title: 'Màu', dataIndex: 'color' },
            { title: 'Dung lượng', dataIndex: 'storage' },
            { title: 'Giá bán', dataIndex: 'price', align: 'right', render: v => currency(v) },
          ];
          return (
            <>
              <Descriptions bordered size="small" column={1}>
                <Descriptions.Item label="Mã đơn">{d.code || `Mobile ID -${d.id}`}</Descriptions.Item>
                <Descriptions.Item label="Ngày">{fmtDate(d.date)}</Descriptions.Item>
                <Descriptions.Item label="Khách hàng">{d.customer_name || '—'}</Descriptions.Item>
                <Descriptions.Item label="Ghi chú">{d.note || '—'}</Descriptions.Item>
              </Descriptions>
              <div style={{ marginTop: 12 }} />
              <Table<MobileOutItem>
                size="small"
                rowKey={(r) => r.imei || `${r.device_name}-${r.color}-${r.storage}-${r.price}`}
                dataSource={d.items || []}
                pagination={false}
                columns={cols}
              />
            </>
          );
        })()
      ) : (
        (() => {
          const s = data as ServiceDetail;
          return (
            <>
              <Descriptions bordered size="small" column={1}>
                <Descriptions.Item label="Mã dịch vụ">{`Service ID - ${s.id}`}</Descriptions.Item>
                <Descriptions.Item label="Ngày">{fmtDate(s.service_date)}</Descriptions.Item>
                <Descriptions.Item label="Khách hàng">{s.customer_name || '—'}</Descriptions.Item>
                <Descriptions.Item label="Dịch vụ">{s.service_name || '—'}</Descriptions.Item>
                <Descriptions.Item label="Bảo hành (tháng)">{s.warranty ?? 0}</Descriptions.Item>
                <Descriptions.Item label="Giá dịch vụ">{currency(s.service_price ?? 0)}</Descriptions.Item>
                <Descriptions.Item label="Chi phí">{currency(s.expense ?? 0)}</Descriptions.Item>
                <Descriptions.Item label="Ghi chú">{s.note || '—'}</Descriptions.Item>
              </Descriptions>
            </>
          );
        })()
      )}
    </Modal>
  );
};

export default OriginDetailModal;
