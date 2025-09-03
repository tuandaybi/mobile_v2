// src/pages/DebtsPage.tsx
import React, { useEffect, useMemo, useState } from "react";
import MainLayout from "../components/layout/MainLayout";
import { Row, Col, Button, Space, message, Modal, Form, InputNumber, Select } from "antd";
import type { ColumnsType } from "antd/es/table";
import PageTable from "@/components/shared/PageTable";
import api from "../../axiosConfig";

// Components
import DebtDetailDrawer, { type DebtRecord, type OpenDebt } from "@/components/debt/DebtDetailDrawer";
import OriginDetailModal, { type MobileOutDetail, type ServiceDetail } from "@/components/debt/OriginDetailModal";

// Zustand
import { useModalStore } from "@/store/modalStore";

// ---------- Helpers ----------
const toNum = (v: any) => {
  if (v == null) return 0;
  const n = Number(String(v).replace(/,/g, ""));
  return Number.isFinite(n) ? n : 0;
};
const currency = (v: any) => `${toNum(v).toLocaleString()} đ`;

const normalizeMobileOut = (raw: any): MobileOutDetail => ({
  id: Number(raw?.id ?? raw?.data?.id ?? 0),
  code: raw?.code ?? raw?.order_code ?? (raw?.id ? `MO-${raw.id}` : undefined),
  customer_name: raw?.customer?.name ?? raw?.customer_name ?? raw?.buyer ?? undefined,
  items: Array.isArray(raw?.items)
    ? raw.items.map((i: any) => ({
        imei: i?.imei ?? i?.mb_imei ?? "",
        device_name: i?.device?.name ?? i?.device_name ?? i?.product_name ?? "",
        color: i?.color?.vi_name ?? i?.color ?? "",
        storage: i?.storage?.size_gb ? `${i.storage.size_gb} GB` : i?.storage ?? "",
        price: toNum(i?.price ?? i?.sale_price ?? i?.amount),
      }))
    : [],
  subtotal: toNum(raw?.subtotal ?? raw?.total ?? raw?.amount),
  paid: toNum(raw?.paid ?? raw?.payment_total ?? 0),
  debt: toNum(raw?.debt ?? raw?.remaining ?? (toNum(raw?.total) - toNum(raw?.paid))),
  date: raw?.date ?? raw?.sale_date,
  note: raw?.note ?? "",
});

const normalizeService = (raw: any): ServiceDetail => ({
  id: Number(raw?.id ?? raw?.data?.id ?? 0),
  service_name: raw?.service_name ?? raw?.name ?? "",
  customer_name: raw?.customer?.name ?? raw?.customer_name ?? "",
  service_date: raw?.service_date ?? raw?.created_at,
  service_price: toNum(raw?.service_price ?? raw?.price ?? raw?.amount),
  expense: toNum(raw?.expense ?? 0),
  paid: toNum(raw?.paid ?? raw?.payment_total ?? 0),
  debt: toNum(raw?.debt ?? raw?.remaining ?? (toNum(raw?.service_price ?? raw?.price) - toNum(raw?.paid))),
  warranty: Number(raw?.warranty ?? 0),
  note: raw?.note ?? "",
});

const DebtsPage: React.FC = () => {
  const { debtDrawer, debtPay, debtOrigin } = useModalStore();

  // Table data
  const [rows, setRows] = useState<DebtRecord[]>([]);
  const [loading, setLoading] = useState(false);

  // Drawer chi tiết
  const [openDebts, setOpenDebts] = useState<OpenDebt[]>([]);
  const [loadingOpenDebts, setLoadingOpenDebts] = useState(false);

  // Form pay
  const [payForm] = Form.useForm<{ debt_id: number; amount: number; note?: string }>();
  const [paying, setPaying] = useState(false);

  // Origin modal
  const [originLoading, setOriginLoading] = useState(false);

  // Pagination + sort
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(14);
  const [total, setTotal] = useState(0);
  const [sortBy, setSortBy] = useState<"customer_name" | "phone" | "number_debt" | "debt_total" | "payment_total" | "total">("total");
  const [sortDir, setSortDir] = useState<"asc" | "desc">("desc");
  const [searchText, setSearchText] = useState("");

  // ----- Fetch summary -----
  const fetchSummary = async (
    keyword = searchText,
    nextPage = page,
    nextPerPage = perPage,
    nextSortBy = sortBy,
    nextSortDir = sortDir
  ) => {
    try {
      setLoading(true);
      const params: any = {
        page: nextPage,
        perPage: nextPerPage,
        sortBy: nextSortBy,
        sortDir: nextSortDir,
      };
      const q = (keyword ?? "").trim();
      if (q) params.q = q;

      const res = await api.get("/debts/summary", { params });

      const payload = res.data;
      const arr: any[] = Array.isArray(payload?.data)
        ? payload.data
        : Array.isArray(payload)
        ? payload
        : [];
      setRows(arr.map((r: any) => ({
        id: Number(r.customer_id),
        customer_id: Number(r.customer_id),
        customer_name: r.customer_name ?? "",
        phone: r.phone ?? "",
        number_debt: Number(r.number_debt ?? 0),
        debt_total: toNum(r.debt_total),
        payment_total: toNum(r.payment_total),
        total: toNum(r.total),
      })));

          // ---- ĐỌC PAGER: hỗ trợ cả 2 kiểu ----
    if (payload?.meta && typeof payload.meta.total === "number") {
      // kiểu Resource: { data, links, meta:{ total, per_page, current_page, ... } }
      setTotal(Number(payload.meta.total));
      setPerPage(Number(payload.meta.per_page));
      setPage(Number(payload.meta.current_page));
    } else if (
      typeof payload?.total === "number" &&
      typeof payload?.per_page !== "undefined"
    ) {
      // kiểu paginator mặc định: { data, total, per_page, current_page, ... }
      setTotal(Number(payload.total));
      setPerPage(Number(payload.per_page));
      setPage(Number(payload.current_page ?? 1));
    } else {
      // fallback
      setTotal(arr.length);
      setPerPage(nextPerPage);
      setPage(1);
    }
    } catch (e: any) {
      console.error(e);
      message.error(e?.response?.data?.message ?? "Lỗi tải dữ liệu công nợ");
    } finally {
      setLoading(false);
    }
  };

  // ----- Fetch các khoản nợ mở theo khách -----
  const fetchOpenDebts = async (customer: DebtRecord) => {
    try {
      setLoadingOpenDebts(true);
      const res = await api.get(`/debts/customer/${customer.customer_id}`, { params: { include_payments: true } });
      const list: any[] = Array.isArray(res.data) ? res.data : [];
      const mapped: OpenDebt[] = list.map((d: any) => ({
        id: Number(d.id),
        note: d.note ?? "",
        created_at: d.created_at,
        amount: toNum(d.amount),
        paid: toNum(d.paid),
        remaining: toNum(d.remaining),
        origin_type: d.origin_type ?? "unknown",
        origin_id: d.origin_id != null ? Number(d.origin_id) : null,
        origin_label: d.origin_label ?? "",
        payments: Array.isArray(d.payments)
          ? d.payments.map((p: any) => ({
              id: Number(p.id),
              amount: toNum(p.amount),
              note: p.note ?? "",
              created_at: p.created_at,
              user_name: p.user?.name ?? p.user_name ?? "",
            }))
          : [],
      }));
      setOpenDebts(mapped);
    } catch (e: any) {
      console.error(e);
      message.error(e?.response?.data?.message ?? "Lỗi tải chi tiết khoản nợ");
    } finally {
      setLoadingOpenDebts(false);
    }
  };

  // lần đầu tải
  useEffect(() => {
    fetchSummary();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // ----- Actions -----
  const handleShowDetail = async (record: DebtRecord) => {
    debtDrawer.open(record);
    await fetchOpenDebts(record);
  };

  const handleSettleAll = async (record: DebtRecord) => {
    Modal.confirm({
      title: `Tất toán toàn bộ công nợ của ${record.customer_name}?`,
      content: `Số còn lại: ${currency(record.total)}`,
      okText: "Tất toán",
      cancelText: "Hủy",
      onOk: async () => {
        try {
          await api.post(`/debts/settle-customer/${record.customer_id}`);
          message.success("Đã tất toán toàn bộ");
          await fetchSummary(); // giữ nguyên bộ lọc/phân trang hiện tại
          const cur = useModalStore.getState().debtDrawer.customer as DebtRecord | null;
          if (cur && cur.customer_id === record.customer_id) {
            await fetchOpenDebts(record);
          }
        } catch (e: any) {
          console.error(e);
          message.error(e?.response?.data?.message ?? "Lỗi tất toán");
        }
      },
    });
  };

  const handlePayClick = (debtId: number, defaultAmount: number) => {
    debtPay.open(debtId, defaultAmount);
  };

  const openOriginDetail = async (d: OpenDebt) => {
    useModalStore.getState().debtOrigin.open({
      originType: d.origin_type ?? "unknown",
      originId: d.origin_id ?? null,
      debtId: d.id,
      debtRemaining: toNum(d.remaining),
      record: null,
    });

    try {
      setOriginLoading(true);
      if (d.origin_type === "mobile" && d.origin_id) {
        const res = await api.get(`/mobile-out/${d.origin_id}`);
        useModalStore.getState().debtOrigin.setRecord(normalizeMobileOut(res.data));
      } else if (d.origin_type === "service" && d.origin_id) {
        const res = await api.get(`/services/${d.origin_id}`);
        useModalStore.getState().debtOrigin.setRecord(normalizeService(res.data));
      } else {
        useModalStore.getState().debtOrigin.setRecord(null);
      }
    } catch (e: any) {
      console.error(e);
      message.error(e?.response?.data?.message ?? "Không tải được chi tiết nguồn");
      useModalStore.getState().debtOrigin.setRecord(null);
    } finally {
      setOriginLoading(false);
    }
  };

  const payRemainingFromOrigin = (debtId: number, amount: number) => {
    useModalStore.getState().debtOrigin.close();
    debtPay.open(debtId, amount);
  };

  // ----- Columns -----
  const columns: ColumnsType<DebtRecord> = useMemo(
    () => [
      {
        title: "Tên khách hàng",
        dataIndex: "customer_name",
        key: "customer_name",
        sorter: true,
        sortOrder: sortBy === "customer_name" ? (sortDir === "asc" ? "ascend" : "descend") : null,
      },
      {
        title: "SĐT",
        dataIndex: "phone",
        key: "phone",
        sorter: true,
        sortOrder: sortBy === "phone" ? (sortDir === "asc" ? "ascend" : "descend") : null,
      },
      {
        title: "Số lượng Debt",
        dataIndex: "number_debt",
        key: "number_debt",
        sorter: true,
        sortOrder: sortBy === "number_debt" ? (sortDir === "asc" ? "ascend" : "descend") : null,
      },
      {
        title: "Tổng tiền",
        dataIndex: "debt_total",
        key: "debt_total",
        render: (v: number) => currency(v),
        sorter: true,
        sortOrder: sortBy === "debt_total" ? (sortDir === "asc" ? "ascend" : "descend") : null,
      },
      {
        title: "Đã trả",
        dataIndex: "payment_total",
        key: "payment_total",
        render: (v: number) => currency(v),
        sorter: true,
        sortOrder: sortBy === "payment_total" ? (sortDir === "asc" ? "ascend" : "descend") : null,
      },
      {
        title: "Còn lại",
        dataIndex: "total",
        key: "total",
        render: (v: number) => <span style={{ color: v > 0 ? "red" : "green" }}>{currency(v)}</span>,
        sorter: true,
        sortOrder: sortBy === "total" ? (sortDir === "asc" ? "ascend" : "descend") : null,
      },
      {
        title: "Chức năng",
        key: "action",
        render: (_, record) => (
          <Space.Compact>
            <Button size="small" onClick={() => handleShowDetail(record)}>Chi tiết</Button>
            <Button danger size="small" onClick={() => handleSettleAll(record)}>Tất toán</Button>
          </Space.Compact>
        ),
      },
    ],
    [sortBy, sortDir]
  );

  // Đồng bộ form pay khi mở
  useEffect(() => {
    if (!debtPay.isOpen) return;
    payForm.setFieldsValue({
      debt_id: debtPay.debtId && debtPay.debtId > 0 ? debtPay.debtId : undefined,
      amount: debtPay.amount ?? undefined,
      note: debtPay.note ?? undefined,
    });
  }, [debtPay.isOpen, debtPay.debtId, debtPay.amount, debtPay.note, payForm]);

  return (
    <MainLayout>
      <Row gutter={16}>
        <Col span={24}>
          <PageTable<DebtRecord>
            title="📒 Quản lý công nợ"
            data={rows}
            loading={loading}
            columns={columns}
            pageSize={perPage}
            rowKey="id"
            onSearch={(q) => {
              setSearchText(q);
              setPage(1);
              fetchSummary(q, 1, perPage, sortBy, sortDir);
            }}
            scrollX="max-content"
            pagination={{
              current: page,
              pageSize: perPage,
              total,
              showSizeChanger: true,
              pageSizeOptions: [10, 14, 20, 30, 50, 100],
            }}
            onTableChange={(pagination, _filters, sorter) => {
              const nextPage = pagination.current || 1;
              const nextPerPage = pagination.pageSize || perPage;

              let nextSortBy = sortBy;
              let nextSortDir: "asc" | "desc" = sortDir;

              const s: any = Array.isArray(sorter) ? sorter[0] : sorter;
              const allow = ["customer_name","phone","number_debt","debt_total","payment_total","total"];
              if (s && s.field && allow.includes(s.field)) {
                nextSortBy = s.field as typeof sortBy;
                nextSortDir = s.order === "ascend" ? "asc" : "desc";
              }

              setPage(nextPage);
              setPerPage(nextPerPage);
              setSortBy(nextSortBy);
              setSortDir(nextSortDir);

              // giữ nguyên search hiện tại
              fetchSummary(searchText, nextPage, nextPerPage, nextSortBy, nextSortDir);
            }}
          />
        </Col>
      </Row>

      {/* Drawer chi tiết */}
      <DebtDetailDrawer
        open={debtDrawer.isOpen}
        onClose={debtDrawer.close}
        customer={debtDrawer.customer as DebtRecord | null}
        loading={loadingOpenDebts}
        debts={openDebts}
        onRefresh={debtDrawer.customer ? () => fetchOpenDebts(debtDrawer.customer as DebtRecord) : undefined}
        onSettleAll={handleSettleAll}
        onPayClick={handlePayClick}
        onOriginClick={openOriginDetail}
      />

      {/* Modal nguồn */}
      <OriginDetailModal
        open={debtOrigin.isOpen}
        loading={originLoading}
        type={debtOrigin.originType}
        id={debtOrigin.originId}
        data={debtOrigin.record as MobileOutDetail | ServiceDetail | null}
        debtId={debtOrigin.debtId ?? undefined}
        debtRemaining={debtOrigin.debtRemaining ?? 0}
        onClose={useModalStore.getState().debtOrigin.close}
        onPayRemaining={payRemainingFromOrigin}
      />

      {/* Modal thanh toán */}
      <Modal
        title="Ghi nhận thanh toán"
        open={debtPay.isOpen}
        onCancel={() => { payForm.resetFields(); debtPay.close(); }}
        okText="Xác nhận"
        onOk={async () => {
          try {
            const { debt_id, amount, note } = await payForm.validateFields();
            setPaying(true);
            await api.post(`/debts/${debt_id}/pay`, { amount, note });
            message.success("Đã ghi nhận thanh toán");
            payForm.resetFields();
            debtPay.close();
            await fetchSummary(searchText, page, perPage, sortBy, sortDir);
            const cur = useModalStore.getState().debtDrawer.customer as DebtRecord | null;
            if (cur) await fetchOpenDebts(cur);
          } catch (e: any) {
            if (e?.errorFields) return;
            console.error(e);
            message.error(e?.response?.data?.message ?? "Lỗi khi thanh toán");
          } finally {
            setPaying(false);
          }
        }}
        confirmLoading={paying}
      >
        <Form form={payForm} layout="vertical">
          <Form.Item name="debt_id" label="Khoản nợ" rules={[{ required: true, message: "Chọn khoản nợ" }]}>
            <Select
              placeholder="Chọn khoản nợ muốn thanh toán"
              options={openDebts.map(d => ({ value: d.id, label: `#${d.id} - Còn lại ${currency(d.remaining)}` }))}
              showSearch
              optionFilterProp="label"
              onChange={(v) => useModalStore.getState().debtPay.setFields({ debtId: v })}
              value={debtPay.debtId && debtPay.debtId > 0 ? debtPay.debtId : undefined}
            />
          </Form.Item>

          <Form.Item name="amount" label="Số tiền thanh toán" rules={[{ required: true, message: "Nhập số tiền" }]}>
            <InputNumber
              min={1}
              step={1000}
              style={{ width: "100%" }}
              onChange={(v) => useModalStore.getState().debtPay.setFields({ amount: Number(v) || 0 })}
              formatter={(v) => String(v ?? "").replace(/\B(?=(\d{3})+(?!\d))/g, ",")}
              value={debtPay.amount ?? undefined}
            />
          </Form.Item>

          <Form.Item name="note" label="Ghi chú">
            <Select
              placeholder="Chọn nhanh hoặc nhập tuỳ chỉnh ở dưới"
              options={[
                { value: "Khách thanh toán tại quầy", label: "Khách thanh toán tại quầy" },
                { value: "Chuyển khoản", label: "Chuyển khoản" },
                { value: "Thanh toán online", label: "Thanh toán online" },
              ]}
              allowClear
              showSearch
              optionFilterProp="label"
              onChange={(v) => useModalStore.getState().debtPay.setFields({ note: v || null })}
              value={debtPay.note ?? undefined}
            />
          </Form.Item>
        </Form>
      </Modal>
    </MainLayout>
  );
};

export default DebtsPage;
