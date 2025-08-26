import React, { useEffect, useMemo, useState } from "react";
import MainLayout from "../components/layout/MainLayout";
import { Row, Col, Button, Space, Popconfirm, message } from "antd";
import type { ColumnsType } from "antd/es/table";
import dayjs from "dayjs";
import { useModalStore } from "../store/modalStore";
import PageTable from "@/components/shared/PageTable";
import api from "@/../axiosConfig";

const fVND = (n: number) => `${Number(n || 0).toLocaleString()} đ`;

const matchKw = (row: any, kw: string) => {
  const hay = [
    row.imei,
    row.country_code,
    row.import_note,
    row.supplier,
    row.device?.name,
    row.color?.en_name,
    row.color?.vi_name,
    row.storage?.name,
  ]
    .filter(Boolean)
    .join(" ")
    .toLowerCase();
  return hay.includes(kw);
};

const formatName = (r: any) => {
  const parts = [
    r.device?.name ?? "Không rõ",
    r.country_code || null,
    r.storage?.name || null,
    r.color?.en_name ? `(${r.color.en_name})` : null,
  ].filter(Boolean);
  return parts.join(" - ");
};

const Mobiles: React.FC = () => {
  // ✅ lấy đúng selector từ store (tránh destructure cả cục)
  const openSell   = useModalStore((s) => s.sellMobile.open);
  const openMobile = useModalStore((s) => s.mobile.open);
  const mobilesVersion = useModalStore(s => s.mobilesVersion);

  const [allRaw, setAllRaw] = useState<any[]>([]);
  const [rows, setRows] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [searchText, setSearchText] = useState("");

  const fetchMobiles = async (keyword?: string) => {
    try {
      setLoading(true);
      const res = await api.get("/mobile-in");
      const list: any[] = Array.isArray(res.data)
        ? res.data
        : Array.isArray(res.data?.data)
        ? res.data.data
        : [];
      setAllRaw(list);
      if (keyword?.trim()) {
        const kw = keyword.toLowerCase();
        setRows(list.filter((r) => matchKw(r, kw)));
      } else {
        setRows(list);
      }
    } catch (e: any) {
      console.error(e);
      message.error(e?.response?.data?.message || "Không tải được dữ liệu MobileIn");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    // mỗi lần version đổi → refetch list (giữ nguyên bộ lọc hiện tại)
    fetchMobiles(searchText);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [mobilesVersion]);

  const handleSearch = (value: string) => {
    setSearchText(value);
    const kw = value.trim().toLowerCase();
    if (!kw) {
      setRows(allRaw); // clear search
      return;
    }
    setRows(allRaw.filter((r) => matchKw(r, kw)));
  };

  // handler gọn: dùng trực tiếp record
  const onSell = (record: any) => {
    openSell(false, record);
  };
  const onEdit = (record: any) => {
    openMobile(true, record);
  };
  const onDelete = async (record: any) => {
    try {
      await api.delete(`/mobile-in/${record.id}`);
      message.success("Xoá thành công");
      await fetchMobiles(searchText);
    } catch (e: any) {
      message.error(e?.response?.data?.message || "Xoá thất bại");
    }
  };


  const columns: ColumnsType<any> = useMemo(
    () => [
      { title: "Tên máy", key: "name", render: (_, r) => formatName(r) },
      { title: "IMEI", dataIndex: "imei", key: "imei" },
      {
        title: "Pin",
        key: "battery_capacity",
        render: (_, r) =>
          typeof r.battery_capacity === "number" ? `${r.battery_capacity}%` : "N/A",
      },
      { title: "Nguồn nhập", dataIndex: "supplier", key: "supplier", render: (v?:string)=>v?.trim()||"—"},
      { title: "Giá nhập", dataIndex: "import_price", key: "import_price", render: (p:any)=>`${fVND(Number(p||0))} đ` },
      { title: "Ngày nhập", dataIndex: "import_date", key: "import_date", render: (d?:string)=>d?dayjs(d).format("DD/MM/YYYY"):"—" },
      { title: "Ghi chú", dataIndex: "import_note", key: "import_note", render: (v?:string)=>v?.trim()||"Không có" },
      {
        title: "Hành động",
        key: "action",
        render: (_, r) => (
          <Space.Compact>
            <Button type="primary" size="small" onClick={() => onSell(r)}>Bán</Button>
            <Button size="small" onClick={() => onEdit(r)}>Sửa</Button>
            <Popconfirm
              title="Xác nhận xoá?"
              onConfirm={() => onDelete(r)}
              okText="Xoá"
              cancelText="Huỷ"
            >
              <Button type="primary" danger size="small">Xoá</Button>
            </Popconfirm>
          </Space.Compact>
        ),
      },
    ],
    [openSell, openMobile] // ✅ không để deps rỗng
  );

  return (
    <MainLayout>
      <Row gutter={16}>
        <Col span={24}>
          <PageTable<any>
            title="📱 Danh sách máy trong kho"
            data={rows}
            columns={columns}
            pageSize={14}
            rowKey="id"
            onSearch={handleSearch}
            scrollX="max-content"
            loading={loading as any}
          />
        </Col>
      </Row>
    </MainLayout>
  );
};

export default Mobiles;
