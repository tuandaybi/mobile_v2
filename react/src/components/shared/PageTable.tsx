import { Card, Input, Space, Table } from "antd";
import type { ReactNode } from "react";
import type { ColumnsType } from "antd/es/table";
import type { SpinProps } from "antd";

type PageTableProps<T extends { id?: string | number }> = {
  title: ReactNode;
  data: T[];
  columns: ColumnsType<T>;
  rowKey?: string | ((record: T) => string);
  pageSize?: number;
  onSearch?: (value: string) => void;
  searchPlaceholder?: string;
  extra?: ReactNode;
  scrollX?: number | string;
  /** spinner của Table */
  loading?: boolean | SpinProps;
  /** skeleton cho Card */
  cardLoading?: boolean;
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
  } = props;

  const isSpinning = typeof loading === "boolean" ? loading : !!loading?.spinning;

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
        pagination={{ pageSize }}
        bordered
        rowClassName={() => "hoverable-row"}
        scroll={{ x: scrollX }}
        loading={loading}
      />
    </Card>
  );
}
