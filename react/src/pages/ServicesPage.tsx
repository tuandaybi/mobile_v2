import React, { useEffect, useMemo, useState } from "react";
import MainLayout from "../components/layout/MainLayout";
import { Row, Col, Button, Space, Popconfirm, message } from "antd";
import type { ColumnsType } from "antd/es/table";
import { PlusOutlined } from "@ant-design/icons";
import dayjs from "dayjs";
import api from "../../axiosConfig";
import { useModalStore } from "../store/modalStore";
import PageTable from "@/components/shared/PageTable";

type Service = {
  id: number;
  name: string;            // Tên dịch vụ
  price: number;           // Tiền thu khách
  customerName: string;    // Tên khách
  phone?: string | null;   // SĐT
  cost: number;            // Chi phí (expense)
  date: string;            // YYYY-MM-DD / ISO
  warranty: string;        // Bảo hành hiển thị
  note?: string | null;    // Ghi chú
};

const fVND = (n: number) =>
  new Intl.NumberFormat("vi-VN", { maximumFractionDigits: 0 }).format(n);

const matchKw = (row: any, kw: string) => {
  const hay = [
    row.name,
    row.price,
    row.customer?.name,
    row.customer?.phone,
    row.cost,
    row.date,
    row.warranty,
    row.note,
  ]
    .filter(Boolean)
    .join(" ")
    .toLowerCase();
  return hay.includes(kw);
};

const warrantyLabel = (months?: number | null) => {
  if (months == null) return "Không bảo hành";
  const m = Number(months);
  return m <= 0 ? "Không bảo hành" : `${m} tháng`;
};

const currency = (n: number) => `${Number(n || 0).toLocaleString()} đ`;

const Services: React.FC = () => {
  const openService = useModalStore((s) => s.service.open);
  const servicesVersion = useModalStore(s => s.servicesVersion);

  const [allRaw, setAllRaw] = useState<any[]>([]);
  const [rows, setRows] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [searchText, setSearchText] = useState("");

  const fetchServices = async (keyword?: string) => {
    try {
      setLoading(true);
      const res = await api.get("/services");
      const list = Array.isArray(res.data?.data)
        ? res.data
        : Array.isArray(res.data?.data)
        ? res.data.data
        : [];
      setAllRaw(list);
      if (keyword?.trim()) {
        const kw = keyword.toLowerCase();
        setRows(list.filter((r: any) => matchKw(r, kw)));
      } else {
        setRows(list);
      }
    } catch (e: any) {
      console.error(e);
      message.error(e?.response?.data?.message || "Không tải được dữ liệu Service");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    // mỗi lần version đổi → refetch list (giữ nguyên bộ lọc hiện tại)
    fetchServices(searchText);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [servicesVersion]);

  const handleSearch = (value: string) => {
    setSearchText(value);
    const kw = value.trim().toLowerCase();
    if (!kw) {
      setRows(allRaw); // clear search
      return;
    }
    setRows(allRaw.filter((r) => matchKw(r, kw)));
  };

  const onEdit = (record: any) => {
    openService(true, record);
  };
  const onDelete = async (record: any) => {
    try {
      await api.delete(`/services/${record.id}`);
      message.success("Xoá thành công");
      await fetchServices(searchText);
    } catch (e: any) {
      message.error(e?.response?.data?.message || "Xoá thất bại");
    }
  };

  const columns: ColumnsType<Service> = useMemo(
      () => [
        { title: "Tên dịch vụ", dataIndex: "name", key: "name" },
        { title: "Tên khách", dataIndex: "customerName", key: "customerName" },
        { title: "SĐT", dataIndex: "phone", key: "phone" },
        {
          title: "Tiền thu khách",
          dataIndex: "price",
          key: "price",
          render: (price: number) => fVND(price),
        },
        {
          title: "Chi phí",
          dataIndex: "cost",
          key: "cost",
          render: (cost: number) => fVND(cost),
        },
        {
          title: "Ngày tháng",
          dataIndex: "date",
          key: "date",
          render: (date: string) =>
            date ? dayjs(date).format("DD/MM/YYYY") : "-",
        },
        { title: "Bảo hành", dataIndex: "warranty", key: "warranty" },
        { title: "Ghi chú", dataIndex: "note", key: "note" },
        {
          title: "Chức năng",
          key: "action",
          render: (_, record) => (
            <Space.Compact>
              <Button
                type="default"
                size="small"
                onClick={() => onEdit(record)}
              >
                Sửa
              </Button>
              <Popconfirm
                title="Xác nhận xoá?"
                onConfirm={() => onDelete(record)}
                okText="Xoá"
                cancelText="Huỷ"
              >
                <Button type="primary" danger size="small" loading={loading}>
                  Xoá
                </Button>
              </Popconfirm>
            </Space.Compact>
          ),
        },
      ],
      [loading]
    );



  const mapApiToRow = (it: any): Service => {
    // tuỳ API: chuẩn hoá các field
    const customer = it.customer || {};
    const price = Number(it.price ?? it.service_price ?? 0);
    const expense = Number(it.expense ?? it.cost ?? 0);
    const debt = Number(it.debt ?? 0);
    const date =
      it.service_date ||
      it.export_date ||
      it.sale_date ||
      it.date ||
      it.created_at ||
      "";
    const wMonths =
      it.warranty_months ?? it.warranty ?? (typeof it.warranty === "number" ? it.warranty : 0);

    return {
      id: Number(it.id),
      name: String(it.name ?? it.service_name ?? ""),
      price,
      customerName: String(
        it.customer_name ?? customer.name ?? it.customerName ?? ""
      ),
      phone:
        it.phone_number ??
        customer.phone_number ??
        customer.phone ??
        it.phone ??
        null,
      cost: expense,
      date,
      warranty: warrantyLabel(Number(wMonths)),
      note: it.note ?? null,
    };
  };


return (
    <MainLayout>
      <Row gutter={16}>
        <Col span={24}>
          <PageTable<Service>
            title="🛠 Danh sách dịch vụ"
            data={rows}
            columns={columns}
            pageSize={14}
            rowKey="id"
            loading={loading}
            onSearch={handleSearch}
            scrollX="max-content"
            extra={
              <Button
                type="primary"
                icon={<PlusOutlined />}
                onClick={() => openService(false, null)}
              >
                Thêm dịch vụ
              </Button>
            }
          />
        </Col>
      </Row>
    </MainLayout>
  );
};

export default Services;
