import React from "react";
import {
  Drawer, List, Button, Tag, Space, Typography, Divider, Statistic,
  Row, Col, Card, Progress, Empty, Tooltip, Popconfirm
} from "antd";
import dayjs from "dayjs";

const { Text } = Typography;

export type OriginType = 'mobile' | 'service' | 'unknown';

export type DebtRecord = {
  id: number; // alias customer_id
  customer_id: number;
  customer_name: string;
  phone: string;
  number_debt: number;
  debt_total: number;
  payment_total: number;
  total: number;
};

export type PaymentItem = {
  id: number;
  amount: number | string;
  note?: string | null;
  created_at: string;         // ISO
  user_name?: string | null;  // người tạo phiếu
};

export type OpenDebt = {
  id: number;
  note?: string | null;
  created_at: string;
  amount: number | string;
  paid: number | string;
  remaining: number | string;
  origin_type?: OriginType;
  origin_id?: number | null;
  origin_label?: string;

  /** 🔥 Thêm: danh sách lần trả cho khoản nợ này */
  payments?: PaymentItem[];
};

type Props = {
  open: boolean;
  onClose: () => void;
  customer: DebtRecord | null;

  loading: boolean;       // loading danh sách khoản nợ đang mở
  debts: OpenDebt[];      // danh sách khoản nợ đang mở của khách (đã kèm payments)

  onRefresh?: () => void; // gọi lại API chi tiết
  onSettleAll?: (customer: DebtRecord) => void;
  onPayClick: (debtId: number, defaultAmount: number) => void;
  onOriginClick: (debt: OpenDebt) => void; // mở modal chi tiết nguồn
};

const toNum = (v: any) => {
  if (v == null) return 0;
  const n = Number(String(v).replace(/,/g, ""));
  return Number.isFinite(n) ? n : 0;
};
const currency = (v: any) => `${toNum(v).toLocaleString()} đ`;
const fmtDate = (s?: string) => (s ? dayjs(s).format("DD/MM/YYYY HH:mm") : "—");
const percentPaid = (amount: any, paid: any) => {
  const a = toNum(amount), p = toNum(paid);
  return a > 0 ? Math.min(100, Math.round((p / a) * 100)) : 0;
};

const DebtDetailDrawer: React.FC<Props> = ({
  open, onClose, customer, loading, debts,
  onRefresh, onSettleAll, onPayClick, onOriginClick
}) => {

  const totals = React.useMemo(() => {
    return debts.reduce(
      (t, d) => ({
        amount: t.amount + toNum(d.amount),
        paid: t.paid + toNum(d.paid),
        remaining: t.remaining + toNum(d.remaining),
        count: t.count + 1,
      }),
      { amount: 0, paid: 0, remaining: 0, count: 0 }
    );
  }, [debts]);

  return (
    <Drawer
      title={
        <div>
          <div style={{ fontWeight: 600 }}>
            {customer ? `Công nợ của ${customer.customer_name}` : "Chi tiết công nợ"}
          </div>
          {customer?.phone && (
            <Text type="secondary">
              SĐT: <Text copyable>{customer.phone}</Text>
            </Text>
          )}
        </div>
      }
      placement="right"
      width={720}
      onClose={onClose}
      open={open}
      extra={
        customer ? (
          <Space>
            {onRefresh && <Button onClick={onRefresh}>Làm mới</Button>}
            {onSettleAll && (
              <Popconfirm
                title="Tất toán toàn bộ?"
                description={`Xác nhận tất toán ${currency(customer.total)} cho khách này.`}
                okText="Tất toán"
                cancelText="Hủy"
                onConfirm={() => onSettleAll(customer)}
              >
                <Button danger>Tất toán</Button>
              </Popconfirm>
            )}
          </Space>
        ) : null
      }
      footer={
        <Row gutter={16} style={{ width: "100%" }}>
          <Col xs={12} md={6}><Statistic title="Số khoản nợ" value={totals.count} /></Col>
          <Col xs={12} md={6}><Statistic title="Gốc (đang mở)" value={currency(totals.amount)} /></Col>
          <Col xs={12} md={6}><Statistic title="Đã trả" value={currency(totals.paid)} /></Col>
          <Col xs={12} md={6}>
            <Statistic
              title="Còn lại"
              valueRender={() => (
                <span style={{ color: totals.remaining > 0 ? "red" : "green" }}>
                  {currency(totals.remaining)}
                </span>
              )}
            />
          </Col>
        </Row>
      }
    >
      <div style={{ marginBottom: 12 }}>
        <Space size="large" wrap>
          <Tag color="geekblue">Khoản nợ: {totals.count}</Tag>
          <Tag>Gốc: {currency(totals.amount)}</Tag>
          <Tag color="blue">Đã trả: {currency(totals.paid)}</Tag>
          <Tag color="red">Còn lại: {currency(totals.remaining)}</Tag>
        </Space>
      </div>
      <Divider style={{ margin: "8px 0 16px" }} />

      <List
        loading={loading}
        dataSource={debts}
        locale={{ emptyText: <Empty description="Không còn khoản nợ nào" /> }}
        renderItem={(d) => {
          const pct = percentPaid(d.amount, d.paid);
          const originColor =
            d.origin_type === 'mobile' ? 'green' :
            d.origin_type === 'service' ? 'purple' : 'default';

          const payments = d.payments ?? [];
          const totalPaidFromItems = payments.reduce((s, p) => s + toNum(p.amount), 0);

          return (
            <List.Item style={{ padding: 0, marginBottom: 12, border: "none" }}>
              <Card
                size="small"
                hoverable
                style={{ width: "100%", borderRadius: 12 }}
                title={
                  <Space direction="vertical" size={0}>
                    <span style={{ fontWeight: 600 }}>#ID Debt-{d.id}</span>
                    <Text type="secondary">{fmtDate(d.created_at)}</Text>
                  </Space>
                }
                extra={
                  <Space>
                    {d.origin_label && (
                      <Tag
                        color={originColor}
                        style={{ cursor: d.origin_type && d.origin_type !== 'unknown' ? 'pointer' : 'default' }}
                        onClick={() => onOriginClick(d)}
                      >
                        {d.origin_label}
                      </Tag>
                    )}
                    <Tooltip title="Thanh toán khoản nợ này">
                      <Button
                        size="small"
                        type="primary"
                        onClick={() => onPayClick(d.id, toNum(d.remaining))}
                      >
                        Trả
                      </Button>
                    </Tooltip>
                  </Space>
                }
              >
                <Row gutter={[12, 12]}>
                  <Col xs={24}>
                    <div style={{ display: "flex", gap: 12, flexWrap: "wrap" }}>
                      <Tag>Gốc: {currency(d.amount)}</Tag>
                      <Tag color="blue">Đã trả: {currency(d.paid)}</Tag>
                      <Tag color="red">Còn lại: <b>{currency(d.remaining)}</b></Tag>
                    </div>
                  </Col>

                  <Col xs={24}>
                    <div style={{ marginTop: 6 }}>
                      <Text type="secondary">Tiến độ thanh toán</Text>
                      <Progress percent={pct} />
                    </div>
                  </Col>

                  {/* 🔥 Chi tiết các lần trả */}
                  <Col xs={24}>
                    <Divider style={{ margin: "8px 0" }} />
                    <Space align="baseline" style={{ width: "100%", justifyContent: "space-between" }}>
                      <Text strong>Chi tiết đã trả</Text>
                      <Text type="secondary">
                        Tổng theo chi tiết: <b>{currency(totalPaidFromItems)}</b>
                      </Text>
                    </Space>

                    {payments.length === 0 ? (
                      <Empty style={{ marginTop: 8 }} description="Chưa có lần trả nào" />
                    ) : (
                      <List
                        size="small"
                        dataSource={payments}
                        renderItem={(p) => (
                          <List.Item key={p.id} style={{ paddingLeft: 0, paddingRight: 0 }}>
                            <div style={{ width: "100%" }}>
                              <Space style={{ width: "100%", justifyContent: "space-between" }} wrap>
                                <Space>
                                  <Tag color="blue">Trả</Tag>
                                  <Text>{currency(p.amount)}</Text>
                                </Space>
                                <Space split={<Divider type="vertical" />}>
                                  <Text type="secondary">{fmtDate(p.created_at)}</Text>
                                  {p.user_name && <Text type="secondary">bởi {p.user_name}</Text>}
                                </Space>
                              </Space>
                              {p.note && (
                                <div style={{ marginTop: 4 }}>
                                  <Text type="secondary">Ghi chú: </Text>
                                  <Text>{p.note}</Text>
                                </div>
                              )}
                            </div>
                          </List.Item>
                        )}
                      />
                    )}
                  </Col>

                  {d?.note ? (
                    <Col xs={24}>
                      <Divider style={{ margin: "8px 0" }} />
                      <Text type="secondary">Ghi chú khoản nợ:</Text>
                      <div>{d.note}</div>
                    </Col>
                  ) : null}
                </Row>
              </Card>
            </List.Item>
          );
        }}
      />
    </Drawer>
  );
};

export default DebtDetailDrawer;
