import React, { useEffect, useMemo, useState } from 'react';
import { Row, Col, Card, Table, Typography, Skeleton } from 'antd';
import { PieChart, Pie, Cell, Tooltip } from 'recharts';
import type { ColumnsType } from 'antd/es/table';
import MainLayout from '../components/layout/MainLayout';
import {
  DollarOutlined,
  ShoppingCartOutlined,
  ShoppingOutlined,
  UserOutlined,
  FundOutlined,
  BarChartOutlined,
} from '@ant-design/icons';
import { Link } from 'react-router-dom';
import api from '../../axiosConfig'; // <-- chỉnh path nếu cần

const { Title } = Typography;
const { Meta } = Card;

/** ===== Types khớp với HomeController@index ===== */
type ByDevice = {
  device_id: number;
  device_name: string;
  qty: number;
  total_value: number;
};
type BestSeller = { device_id: number; device_name: string; sold_qty: number } | null;
type OldestStock = { mobile_in_id: number; device_name: string; created_at: string; days_in_stock: number } | null;

type ApiData = {
  inventory: {
    by_device: ByDevice[];
    total_value: number;
    total_qty: number;
    top_device: { device_id: number; device_name: string; qty: number } | null;
    oldest_stock: OldestStock;
  };
  sales: {
    total_revenue: number;         // all-time
    profit: number;                // all-time
    best_seller_all_time: BestSeller;
    this_month: {
      total_revenue: number;
      profit: number;
      best_seller: BestSeller;
    };
  };
  service: {
    total_revenue: number;         // all-time
    profit: number;                // all-time
    this_month: {
      total_revenue: number;
      profit: number;
    };
  };
  customers: {
    total_customers: number;
    total_debt: number;
    total_paid: number;
    outstanding_debt: number;      // nợ - chưa trả
  };
};

/** ===== Utils ===== */
function randomColor(index: number) {
  const colors = [
    '#0088FE', '#00C49F', '#FFBB28', '#FF8042',
    '#A28FD0', '#FF6666', '#00CED1', '#7CFC00',
    '#FFD700', '#DC143C', '#FF69B4', '#4B0082',
    '#20B2AA', '#8B0000', '#FF8C00'
  ];
  return colors[index % colors.length];
}

const money = (v: number | undefined | null) =>
  (Number(v || 0)).toLocaleString('vi-VN') + ' đ';

interface ProductRow {
  name: string;
  quantity: number;
  money: number;
  color: string;
}

const DashboardPage: React.FC = () => {
  const [data, setData] = useState<ApiData | null>(null);
  const [loading, setLoading] = useState(true);

  // Fetch dữ liệu dashboard
  useEffect(() => {
    (async () => {
      try {
        const res = await api.get('/home'); // route trả về HomeController@index
        setData(res.data as ApiData);
      } catch (e) {
        console.error(e);
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  // Chuyển inventory.by_device -> productData (cho Table)
  const productData: ProductRow[] = useMemo(() => {
    if (!data) return [];
    return data.inventory.by_device.map((it, idx) => ({
      name: it.device_name,
      quantity: it.qty,
      money: it.total_value,
      color: randomColor(idx)
    }));
  }, [data]);

  // Dữ liệu donut
  const donutData = useMemo(() => {
    return productData.map(p => ({
      name: p.name,
      value: p.quantity,
      color: p.color,
      money: money(p.money),
    }));
  }, [productData]);

  const totalQuantity = data?.inventory.total_qty ?? 0;
  const totalMoney = data?.inventory.total_value ?? 0;

  // Columns Table
  const productColumns: ColumnsType<ProductRow> = [
    {
      title: 'Tên sản phẩm',
      dataIndex: 'name',
      key: 'name',
      render: (_, record) => record.name,
    },
    { title: 'Số lượng', dataIndex: 'quantity', key: 'quantity' },
    {
      title: 'Tổng tiền',
      dataIndex: 'money',
      key: 'money',
      render: (v: number) => money(v),
    },
  ];

  // Stats cards (trên cùng)
  const dashboardStats = [
    { title: 'Tiền hàng tồn kho', amount: money(totalMoney), icon: <DollarOutlined />, color: '#0e701dff', link: '/mobiles' },
    { title: 'Tiền bán ĐT tháng', amount: money(data?.sales.this_month.total_revenue), icon: <ShoppingCartOutlined />, color: '#0c79c1ff', link: '/sold-products' },
    { title: 'Tiền DV tháng', amount: money(data?.service.this_month.total_revenue), icon: <ShoppingOutlined />, color: '#0e7066ff', link: '/services' },
    { title: 'Tiền công nợ', amount: money(data?.customers.outstanding_debt), icon: <UserOutlined />, color: '#e1222bff', link: '/debts' },
    { title: 'Lợi nhuận ĐT tháng', amount: money(data?.sales.this_month.profit), icon: <BarChartOutlined />, color: '#0c79c1ff', link: '#' },
    { title: 'Lợi nhuận DV tháng', amount: money(data?.service.this_month.profit), icon: <FundOutlined />, color: '#0e7066ff', link: '#' },
  ];

  // Extra cards (dưới cùng)
  const dashboardExtra = [
    {
      title: 'Sản phẩm bán chạy nhất',
      amount: data?.sales.best_seller_all_time
        ? `${data.sales.best_seller_all_time.device_name} (${data.sales.best_seller_all_time.sold_qty})`
        : '-',
      icon: <UserOutlined />
    },
    {
      title: 'Sản phẩm bán chạy nhất tháng',
      amount: data?.sales.this_month.best_seller
        ? `${data.sales.this_month.best_seller.device_name} (${data.sales.this_month.best_seller.sold_qty})`
        : '-',
      icon: <BarChartOutlined />
    },
    {
      title: 'Sản phẩm tồn kho nhiều nhất',
      amount: data?.inventory.top_device
        ? `${data.inventory.top_device.device_name} (${data.inventory.top_device.qty})`
        : '-',
      icon: <FundOutlined />
    },
    {
      title: 'Số lượng khách hàng',
      amount: data?.customers.total_customers ?? 0,
      icon: <UserOutlined />
    },
  ];

  return (
    <MainLayout>
      <Row gutter={[16, 16]}>
        {dashboardStats.map((stat, index) => (
          <Col xs={24} sm={12} md={8} key={`stat-${index}`}>
            <Card hoverable loading={loading}>
              <Meta
                avatar={<div style={{ fontSize: 35, color: stat.color }}>{stat.icon}</div>}
                title={stat.title}
                description={
                  loading ? (
                    <Skeleton active paragraph={false} title={{ width: 100 }} />
                  ) : (
                    <Title level={5}>
                      <Link to={stat.link}>{stat.amount}</Link>
                    </Title>
                  )
                }
              />
            </Card>
          </Col>
        ))}
      </Row>

      <Row gutter={[16, 16]} style={{ marginTop: 32 }}>
        <Col xs={24} sm={24} lg={8}>
          <Card loading={loading} >
            <div style={{ display: 'flex', justifyContent: 'center', position: 'relative', overflow: 'visible', zIndex: 2 }}>
              {/* Giữ kích thước như cũ để không đổi layout của mày */}
              <PieChart width={600} height={600}>
                <Pie
                  data={donutData}
                  dataKey="value"
                  nameKey="name"
                  innerRadius={180}
                  outerRadius={260}
                  label={false}
                >
                  {donutData.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={entry.color} />
                  ))}
                </Pie>

                {/* Center text */}
                <text
                  x={600 / 2}
                  y={600 / 2}
                  textAnchor="middle"
                  dominantBaseline="middle"
                  style={{ fontSize: 30, fontWeight: 'bold' }}
                >
                  <Link to="/mobiles">{totalQuantity} SẢN PHẨM</Link>
                </text>

                <Tooltip
                  content={({ active, payload }) => {
                    if (active && payload && payload.length) {
                      const d = payload[0].payload as { name: string; value: number; money: string };
                      return (
                        <div style={{ background: '#fff', padding: 10, border: '1px solid #ccc', borderRadius: 10 }}>
                          <p><strong>{d.name}</strong></p>
                          <p>Số lượng: {d.value}</p>
                          <p>Tổng tiền: {d.money}</p>
                        </div>
                      );
                    }
                    return null;
                  }}
                />
              </PieChart>
            </div>
          </Card>
        </Col>

        <Col xs={24} sm={24} lg={16}>
          <Card title="Danh sách sản phẩm trong kho" style={{ height: 650, overflow: 'auto' }} loading={loading}>
            <Table
              dataSource={productData}
              columns={productColumns}
              rowKey="name"
              pagination={false}
              scroll={{ x: 'max-content', y: 450 }}
              rowClassName={(_) => ''}
            />
          </Card>
        </Col>
      </Row>

      <Row gutter={[16, 16]} style={{ marginTop: 32 }}>
        {dashboardExtra.map((stat, index) => (
          <Col xs={24} sm={12} md={6} key={`extra-${index}`}>
            <Card hoverable loading={loading}>
              <Meta
                avatar={<div style={{ fontSize: 24 }}>{stat.icon}</div>}
                title={stat.title}
                description={
                  loading ? (
                    <Skeleton active paragraph={false} title={{ width: 120 }} />
                  ) : (
                    <Title level={5}>{stat.amount}</Title>
                  )
                }
              />
            </Card>
          </Col>
        ))}
      </Row>
    </MainLayout>
  );
};

export default DashboardPage;
