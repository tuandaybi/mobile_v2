import { Card, Input, Space, Table } from "antd";
import type { ReactNode } from "react";
import type { ColumnsType, TablePaginationConfig, TableProps } from "antd/es/table";
import type { SpinProps } from "antd";

type PageTableProps<T extends { id?: string | number }> = {
  title: ReactNode;
  data: T[];
  columns: ColumnsType<T>;
  rowKey?: string | ((record: T) => string);
  pageSize?: number;

  /** Server search */
  onSearch?: (value: string) => void;
  searchPlaceholder?: string;
  extra?: ReactNode;
  scrollX?: number | string;

  /** spinner của Table */
  loading?: boolean | SpinProps;
  /** skeleton cho Card */
  cardLoading?: boolean;

  /** --- Legacy server pagination fields (optional) --- */
  current?: number;
  total?: number;
  showSizeChanger?: boolean;
  pageSizeOptions?: Array<number | string>;
  onPageChange?: (page: number, pageSize: number) => void;

  /** Hiển thị tổng ở pagination */
  showTotal?: boolean | ((total: number, range: [number, number]) => ReactNode);

  /** --- NEW: pass-through (controlled) --- */
  pagination?: TablePaginationConfig | false;
  onTableChange?: TableProps<T>["onChange"];
};

export default function PageTable<T extends { id?: string | number }>(
  props: PageTableProps<T>
) {
  const {
    title,
    data,
    columns,
    rowKey = "id",
    pageSize = 14,

    onSearch,
    searchPlaceholder = "Tìm kiếm ...",
    extra,
    scrollX = "max-content",

    loading = false,
    cardLoading = false,

    // legacy pagination (Mode B)
    current,
    total,
    showSizeChanger = true,
    pageSizeOptions = [10, 15, 20, 50, 100],
    onPageChange,

    showTotal = true,

    // pass-through (Mode A)
    pagination: paginationProp,
    onTableChange,
  } = props;

  const isSpinning =
    typeof loading === "boolean" ? loading : !!(loading as SpinProps)?.spinning;

  // Mode B: tự build pagination khi KHÔNG truyền props.pagination
  const builtPagination: TablePaginationConfig = {
    current,
    pageSize,
    total,
    showSizeChanger,
    pageSizeOptions: pageSizeOptions.map(String),
    // Tránh double fetch: chỉ dùng onPageChange nếu KHÔNG có onTableChange
    onChange: !onTableChange ? onPageChange : undefined,
    showTotal:
      showTotal === true
        ? (t, r) => `${r[0]}-${r[1]} / ${t}`
        : typeof showTotal === "function"
        ? showTotal
        : undefined,
  };

  // Quy tắc chọn pagination:
  // - props.pagination === false  -> tắt phân trang
  // - props.pagination là object -> pass-through
  // - undefined                  -> dùng builtPagination (legacy)
  const finalPagination: TablePaginationConfig | false =
    typeof paginationProp !== "undefined" ? paginationProp : builtPagination;

  return (
    <Card title={title} extra={extra} loading={cardLoading}>
      {onSearch && (
        <Space style={{ marginBottom: 16 }}>
          <Input.Search
            placeholder={searchPlaceholder}
            style={{ width: 300 }}
            onSearch={onSearch}
            allowClear
            disabled={isSpinning}
          />
        </Space>
      )}

      <Table<T>
        dataSource={data}
        columns={columns}
        rowKey={rowKey as any}
        bordered
        rowClassName={() => "hoverable-row"}
        scroll={{ x: scrollX }}
        loading={loading}
        pagination={finalPagination}
        onChange={onTableChange}
      />
    </Card>
  );
}
