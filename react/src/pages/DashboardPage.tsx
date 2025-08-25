import React from 'react';
import { Row, Col, Card, Table, Typography } from 'antd';
import { PieChart, Pie, Cell, Tooltip } from 'recharts';
import type  { ColumnsType } from 'antd/es/table';
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

const { Title } = Typography;
const { Meta } = Card;

type Mobile = {
  id: number;
  name: string;
  imei: string;
  battery: string;
  importSource: string;
  importPrice: number;
  importDate: string;
  note: string;
};


const mockData: Mobile[] = Array.from({ length: 100 }, (_, i) => {
  const models = [
    'iPhone 12', 'iPhone 12 Pro', 'iPhone 12 Pro Max',
    'iPhone 13', 'iPhone 13 Pro', 'iPhone 13 Pro Max',
    'iPhone 14', 'iPhone 14 Plus', 'iPhone 14 Pro', 'iPhone 14 Pro Max',
    'iPhone 15', 'iPhone 15 Plus', 'iPhone 15 Pro', 'iPhone 15 Pro Max'
  ];
  const importSources = ['Cửa hàng A', 'Cửa hàng B', 'Cửa hàng C', 'Kho chính', 'Nhập khẩu', 'Đại lý'];
  
  const randomModel = models[Math.floor(Math.random() * models.length)];
  const randomSource = importSources[Math.floor(Math.random() * importSources.length)];
  const randomBattery = `${Math.floor(Math.random() * 21) + 80}%`; // 80% - 100%
  const randomPrice = Math.floor(Math.random() * 10_000_000) + 5_000_000; // 5tr - 15tr
  const randomDate = new Date(
    2025,
    Math.floor(Math.random() * 8), // tháng 0 - 7
    Math.floor(Math.random() * 28) + 1
  ).toISOString().split('T')[0];

  return {
    id: i + 1,
    name: randomModel,
    imei: String(100000000000000 + Math.floor(Math.random() * 900000000000000)),
    battery: randomBattery,
    importSource: randomSource,
    importPrice: randomPrice,
    importDate: randomDate,
    note: Math.random() > 0.5 ? 'Máy đẹp' : 'Có trầy xước nhẹ',
  };
});

interface Product {
  name: string;
  quantity: number;
  money: number;
  color: string;
}

// Chuyển đổi mockData sang productData
const productData = mockData.reduce((acc, item) => {
  const existing = acc.find(p => p.name === item.name);
  if (existing) {
    existing.quantity += 1;
    existing.money += item.importPrice;
  } else {
    acc.push({
      name: item.name,
      quantity: 1,
      money: item.importPrice,
      color: randomColor(item.id) // sẽ gán sau
    });
  }
  return acc;
}, [] as { name: string; quantity: number; money: number; color: string }[]);

// Hàm tạo màu
function randomColor(index: number) {
  const colors = [
    '#0088FE', '#00C49F', '#FFBB28', '#FF8042',
    '#A28FD0', '#FF6666', '#00CED1', '#7CFC00',
    '#FFD700', '#DC143C', '#FF69B4', '#4B0082',
    '#20B2AA', '#8B0000', '#FF8C00'
  ];
  return colors[index % colors.length];
}

const donutData = productData.map(p => ({
  name: p.name,
  value: p.quantity,
  color: p.color,
  money: `${p.money.toLocaleString()} đ`,
}));

const totalQuantity = productData.reduce((sum, item) => sum + item.quantity, 0);
const totalMoney = productData.reduce((sum, item) => sum + item.money, 0);

const productColumns: ColumnsType<Product> = [
  { title: 'Tên sản phẩm',
     dataIndex: 'name',
      key: 'name',
      render: (_, record) => (
      <Link to={`/mobiles/${record.name}`}>{record.name}</Link>
      ),
  },
  { title: 'Số lượng', dataIndex: 'quantity', key: 'quantity' },
  {
    title: 'Tổng tiền',
    dataIndex: 'money',
    key: 'money',
    render: (money: number) => `${money.toLocaleString()} đ`,
  },
];

const dashboardStats = [
  { title: 'Tiền hàng tồn kho', amount: `${totalMoney.toLocaleString()} đ`, icon: <DollarOutlined />, color: '#0e701dff', link: '/mobiles' },
  { title: 'Tiền bán ĐT tháng' , amount: `${'150123123'.toLocaleString()} đ`, icon: <ShoppingCartOutlined />, color: '#0c79c1ff', link: '/sold' },
  { title: 'Tiền DV tháng', amount: '35123123', icon: <ShoppingOutlined />, color: '#0e7066ff', link: '/services' },
  { title: 'Tiền công nợ', amount: '10123123', icon: <UserOutlined />, color: '#e1222bff', link: '/debts' },
  { title: 'Lợi nhuận ĐT tháng ', amount: '500123123', icon: <BarChartOutlined />, color: '#0c79c1ff', link: '/mobile-profits' },
  { title: 'Lợi nhuận DV tháng', amount: '456123123', icon: <FundOutlined />, color: '#0e7066ff', link: '/service-profits' },
];
const dashboardExtra = [
  { title: 'Sản phẩm bán chạy nhất', amount: '120', icon: <UserOutlined /> },
  { title: 'Sản phẩm bán chạy nhất tháng', amount: '3,200', icon: <BarChartOutlined /> },
  { title: 'Sản phẩm tồn kho nhiều nhất', amount: '2.5%', icon: <FundOutlined /> },
  { title: 'Sản phẩm tồn kho lâu nhất', amount: '25', icon: <ShoppingOutlined /> },
];

const DashboardPage: React.FC = () => {
  return (
    <MainLayout>

      <Row gutter={[16, 16]}>
        {dashboardStats.map((stat, index) => (
          <Col xs={24} sm={12} md={8} key={`extra-${index}`}>
            <Card hoverable>
              <Meta
                avatar={<div style={{ fontSize: 35, color: stat.color }}>{stat.icon}</div>}
                title={stat.title}
                description={<Title level={5}><Link to={stat.link}>{stat.amount}</Link></Title>}
              />
            </Card>
          </Col>
        ))}
      </Row>

      <Row gutter={[16, 16]} style={{ marginTop: 32 }}>
        <Col xs={24} sm={24} lg={8}>
          <Card>
            <div style={{ display: 'flex', justifyContent: 'center'}}>
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
                <text
                  x={600 / 2}
                  y={600 / 2}
                  textAnchor="middle"
                  dominantBaseline="middle"
                  style={{ fontSize: 30, fontWeight: 'bold'}}
                >
                  <Link to={"/mobiles"}>{totalQuantity} SẢN PHẨM</Link>
                </text>
                <Tooltip content={({ active, payload }) => {
                  if (active && payload && payload.length) {
                    const data = payload[0].payload;
                    return (
                      <div style={{ backgroundColor: '#fff', padding: 10, border: '1px solid #ccc', borderRadius: 10 }}>
                        <p><strong>{data.name}</strong></p>
                        <p>Số lượng: {data.value}</p>
                        <p>Tổng tiền: {data.money}</p>
                      </div>
                    );
                  }
                  return null;
                }} />

              </PieChart>
            </div>
          </Card>
        </Col>

        <Col xs={24} sm={24} lg={16}>
          <Card
            title="Danh sách sản phẩm trong kho"
            style={{ height: 650, overflow: 'auto' }}
          >
            <Table
              dataSource={productData}
              columns={productColumns}
              rowKey="name"
              pagination={false}
              scroll={{x: 'max-content', y: 450 }}
              rowClassName={(_) => ''}
            />

          </Card>
        </Col>
      </Row>

      <Row gutter={[16, 16]} style={{ marginTop: 32 }}>
        {dashboardExtra.map((stat, index) => (
          <Col xs={24} sm={12} md={6} key={`extra-${index}`}>
            <Card hoverable>
              <Meta
                avatar={<div style={{ fontSize: 24 }}>{stat.icon}</div>}
                title={stat.title}
                description={<Title level={5}>{stat.amount}</Title>}
              />
            </Card>
          </Col>
        ))}
      </Row>
    </MainLayout>
  );
};

export default DashboardPage;
