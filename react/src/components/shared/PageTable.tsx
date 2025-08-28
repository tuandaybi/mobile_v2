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

  /** --- Server pagination --- */
  current?: number;
  total?: number;
  showSizeChanger?: boolean;
  pageSizeOptions?: Array<number | string>;
  onPageChange?: (page: number, pageSize: number) => void;

  /** --- Table onChange để bắt sort/filter --- */
  onTableChange?: TableProps<T>["onChange"];
  showTotal?:
    | boolean
    | ((total: number, range: [number, number]) => ReactNode);
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

    // server pagination
    current,
    total,
    showSizeChanger = true,
    pageSizeOptions = [10, 15, 20, 50, 100],
    onPageChange,

    onTableChange,
    showTotal = true,
  } = props;

  const isSpinning = typeof loading === "boolean" ? loading : !!loading?.spinning;

  const pagination: TablePaginationConfig = {
    current,
    pageSize,
    total,
    showSizeChanger,
    pageSizeOptions: pageSizeOptions.map(String),
    onChange: onPageChange,
    showTotal:
      showTotal === true
        ? (t, r) => `${r[0]}-${r[1]} / ${t}`
        : typeof showTotal === "function"
        ? showTotal
        : undefined,
  };

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
        rowKey={rowKey}
        bordered
        rowClassName={() => "hoverable-row"}
        scroll={{ x: scrollX }}
        loading={loading}
        pagination={pagination}
        onChange={onTableChange}
      />
    </Card>
  );
}
