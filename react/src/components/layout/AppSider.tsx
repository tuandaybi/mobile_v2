import React from 'react';
import { Layout, Menu } from 'antd';
import { useNavigate, useLocation } from 'react-router-dom';
import type { MenuProps } from 'antd';
import api from '@/../axiosConfig';
import {
  DashboardOutlined,
  MobileOutlined,
  ToolOutlined,
  DollarOutlined,
  FileDoneOutlined,
  BarcodeOutlined,
  BarChartOutlined,
  FileTextOutlined,
  FileExcelOutlined,
  UserOutlined,
  AppstoreOutlined,
  BgColorsOutlined,
  DatabaseOutlined,
  LogoutOutlined,
  ShopOutlined,
  TeamOutlined,
  AccountBookOutlined,
  NotificationOutlined,
} from '@ant-design/icons';

const { Sider } = Layout;

interface AppSiderProps {
  collapsed: boolean;
  onCollapse: (collapsed: boolean) => void;
}

const AppSider: React.FC<AppSiderProps> = ({ collapsed, onCollapse }) => {
  const navigate = useNavigate();
  const location = useLocation();

  const onMenuClick = async ({ key }: { key: string }) => {
    if (key === 'logout') {
      try { await api.post('/logout'); } catch {}
      localStorage.clear();
      navigate('/login');
    } else {
      navigate(key);
    }
  };

  const menuItems: MenuProps['items'] = [
    {
      type: 'group',
      label: 'DANH MỤC',
      children: [
        { key: '/home', icon: <DashboardOutlined />, label: 'Trang chính' },
        { key: '/mobiles', icon: <MobileOutlined />, label: 'Quản lý điện thoại' },
        { key: '/services', icon: <ToolOutlined />, label: 'Quản lý dịch vụ' },
        { key: '/debts', icon: <DollarOutlined />, label: 'Quản lý công nợ' },
        { key: '/sold-products', icon: <FileDoneOutlined />, label: 'Sản phẩm đã bán' },
        { key: '/check-imei', icon: <BarcodeOutlined />, label: 'Check thông tin SP' },
      ],
    },
    {
      type: 'group',
      label: 'BÁO CÁO',
      children: [
        { key: '/report-profit', icon: <BarChartOutlined />, label: 'Báo cáo lợi nhuận' },
        { key: '/report-quantity', icon: <FileTextOutlined />, label: 'Báo cáo sản lượng' },
        { key: '/report-debt', icon: <AccountBookOutlined />, label: 'Báo cáo công nợ' },
        { key: '#invoices', icon: <FileExcelOutlined />, label: 'Xuất Hóa Đơn' },
      ],
    },
    {
      type: 'group',
      label: 'QUẢN TRỊ',
      children: [
        { key: '/admin/users', icon: <UserOutlined />, label: 'Quản lý user' },
        { key: '/admin/stores', icon: <ShopOutlined />, label: 'Quản lý cửa hàng' },
        { key: '/admin/customers', icon: <TeamOutlined />, label: 'Quản lý khách hàng' },
        { key: '/admin/devices', icon: <AppstoreOutlined />, label: 'Quản lý sản phẩm' },
        { key: '/admin/colors', icon: <BgColorsOutlined />, label: 'Quản lý màu sản phẩm' },
        { key: '/admin/notifications', icon: <NotificationOutlined />, label: 'Quản lý thông báo' },
        { key: '/admin/backups', icon: <DatabaseOutlined />, label: 'Quản lý sao lưu' },
        { key: 'logout', icon: <LogoutOutlined />, label: 'Đăng xuất' },
      ],
    },
  ];

  return (
    <Sider 
        collapsible 
        collapsed={collapsed} 
        onCollapse={onCollapse} 
        breakpoint="lg"
        collapsedWidth={0}
        onBreakpoint={(broken) => onCollapse(broken)}
        width={260}>
      <div style={{ color: '#fff', padding: 16, fontWeight: 'bold', textAlign: 'center', fontSize: 18 }}>
        {(() => {
          const user = localStorage.getItem('user');
          try {
            return user ? JSON.parse(user).store_name || 'Mobile Shop' : 'Mobile Shop';
          } catch {
            return 'Mobile Shop';
          }
        })()}
      </div>
      <Menu
        mode="inline"
        theme="dark"
        selectedKeys={[location.pathname]}
        items={menuItems}
        onClick={onMenuClick}
      />
    </Sider>
  );
};

export default AppSider;
