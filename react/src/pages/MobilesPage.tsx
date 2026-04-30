// src/pages/MobilesPage.tsx
import React, { useEffect, useMemo, useState } from "react";
import MainLayout from "../components/layout/MainLayout";
import { Row, Col, Button, Space, Popconfirm, message } from "antd";
import type { ColumnsType } from "antd/es/table";
import dayjs from "dayjs";
import { useModalStore } from "../store/modalStore";
import PageTable from "@/components/shared/PageTable";
import api from "axiosConfig";

const fVND = (n: number) => `${Number(n || 0).toLocaleString()} đ`;

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
  // ✅ TẤT CẢ HOOK PHẢI NẰM TRONG THÂN COMPONENT
  const openSell        = useModalStore((s) => s.sellMobile.open);
  const openMobile      = useModalStore((s) => s.mobile.open);
  const mobilesVersion  = useModalStore((s) => s.mobilesVersion);

  const [rows, setRows] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [searchText, setSearchText] = useState("");

  // 🔽 Thêm state pagination/sort server-side
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(14);
  const [total, setTotal] = useState(0);
  const [sortBy, setSortBy] = useState<'id'|'imei'|'import_date'|'import_price'|'is_sold'>('id');
  const [sortDir, setSortDir] = useState<'asc'|'desc'>('desc');

  const fetchMobiles = async (
    keyword?: string,
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
      const q = (keyword ?? searchText).trim();
      if (q) params.q = q;

      const res = await api.get("/mobile-in", { params });

      const list: any[] = Array.isArray(res.data?.data) ? res.data.data : [];
      setRows(list);
      const meta = res.data?.meta || {};
      setTotal(Number(meta.total || 0));
      setPage(Number(meta.current_page || nextPage));
      setPerPage(Number(meta.per_page || nextPerPage));
    } catch (e: any) {
      console.error(e);
      message.error(e?.response?.data?.message || "Không tải được dữ liệu MobileIn");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    // mỗi lần version đổi → refetch list (giữ nguyên bộ lọc/trang hiện tại)
    fetchMobiles(searchText, page, perPage, sortBy, sortDir);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [mobilesVersion]);

  const handleSearch = (value: string) => {
    setSearchText(value);
    const kw = value.trim();
    setPage(1); // reset về trang 1 khi tìm kiếm
    fetchMobiles(kw, 1, perPage, sortBy, sortDir);
  };

  const onSell = (record: any) => openSell(false, record);
  const onEdit = (record: any) => openMobile(true, record);
  const onDelete = async (record: any) => {
    try {
      await api.delete(`/mobile-in/${record.id}`);
      message.success("Xoá thành công");
      await fetchMobiles(searchText, page, perPage, sortBy, sortDir);
    } catch (e: any) {
      message.error(e?.response?.data?.message || "Xoá thất bại");
    }
  };

  const columns: ColumnsType<any> = useMemo(() => [
    { title: "Tên máy", key: "name", render: (_, r) => formatName(r) },
    {
      title: "IMEI",
      dataIndex: "imei",
      key: "imei",
      sorter: true,
      sortOrder: sortBy==='imei' ? (sortDir==='asc' ? 'ascend' : 'descend') : null,
    },
    {
      title: "Pin",
      key: "battery_capacity",
      render: (_, r) =>
        typeof r.battery_capacity === "number" ? `${r.battery_capacity}%` : "N/A",
    },
    { title: "Nguồn nhập", dataIndex: "supplier", key: "supplier", render: (v?:string)=>v?.trim()||"—"},
    {
      title: "Giá nhập",
      dataIndex: "import_price",
      key: "import_price",
      render: (p:any)=>`${fVND(Number(p||0))}`,
      sorter: true,
      sortOrder: sortBy==='import_price' ? (sortDir==='asc' ? 'ascend' : 'descend') : null,
    },
    {
      title: "Ngày nhập",
      dataIndex: "import_date",
      key: "import_date",
      render: (d?:string)=>d?dayjs(d).format("DD/MM/YYYY"):"—",
      sorter: true,
      sortOrder: sortBy==='import_date' ? (sortDir==='asc' ? 'ascend' : 'descend') : null,
    },
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
  ], [openSell, openMobile, sortBy, sortDir]);

  return (
    <MainLayout>
      <Row gutter={16}>
        <Col span={24}>
          <PageTable<any>
            title="📱 Danh sách máy trong kho"
            data={rows}
            columns={columns}
            pageSize={perPage}
            rowKey="id"
            onSearch={handleSearch}
            scrollX="max-content"
            loading={loading as any}

            // 🔽 THÊM pagination + onTableChange (Cách 1)
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
              let nextSortDir: 'asc'|'desc' = sortDir;

              const s: any = Array.isArray(sorter) ? sorter[0] : sorter;
              if (s && s.field && ['id','imei','import_date','import_price','is_sold'].includes(s.field)) {
                nextSortBy = s.field;
                nextSortDir = s.order === 'ascend' ? 'asc' : 'desc';
              }

              setPage(nextPage);
              setPerPage(nextPerPage);
              setSortBy(nextSortBy as any);
              setSortDir(nextSortDir);
              fetchMobiles(searchText, nextPage, nextPerPage, nextSortBy as any, nextSortDir);
            }}
          />
        </Col>
      </Row>
    </MainLayout>
  );
};

export default Mobiles;
