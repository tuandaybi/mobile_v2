import React, { useMemo } from 'react';
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
  SettingOutlined,
  WalletOutlined,
} from '@ant-design/icons';

const { Sider } = Layout;

interface AppSiderProps {
  collapsed: boolean;
  onCollapse: (collapsed: boolean) => void;
}

const MENU_PERMISSIONS: Record<string, string> = {
  '/home': 'trangchinh',
  '/mobiles': 'dienthoai.xemmua',
  '/services': 'dichvu.xem',
  '/expenses': 'chiphi.xem',
  '/debts': 'congno.xem',
  '/sold-products': 'dienthoai.xemban',
  '/check-imei': 'checkimei.xem',
  '/report-profit': 'baocaoloinhuan.xem',
  '/report-quantity': 'baocaosanluong.xem',
  '/report-debt': 'congno.xem',
  '/admin/users': 'admin.users',
  '/admin/stores': 'admin.cuahang',
  '/admin/customers': 'admin.khachhang',
  '/admin/devices': 'admin.sanpham',
  '/admin/colors': 'admin.mausanpham',
  '/admin/notifications': 'admin.thongbao',
  '/admin/backups': 'admin.saoluu',
  '/admin/settings': 'admin.saoluu',
};

function getUserPermissions(): string[] {
  try {
    const user = localStorage.getItem('user');
    if (!user) return [];
    const parsed = JSON.parse(user);
    return Array.isArray(parsed.permissions) ? parsed.permissions : [];
  } catch {
    return [];
  }
}

const AppSider: React.FC<AppSiderProps> = ({ collapsed, onCollapse }) => {
  const navigate = useNavigate();
  const location = useLocation();

  const permissions = useMemo(() => getUserPermissions(), []);

  const hasPermission = (key: string) => {
    const required = MENU_PERMISSIONS[key];
    if (!required) return true;
    return permissions.includes(required);
  };

  const onMenuClick = async ({ key }: { key: string }) => {
    if (key === 'logout') {
      try { await api.post('/logout'); } catch {}
      localStorage.clear();
      navigate('/login');
    } else {
      navigate(key);
    }
  };

  const filterItems = (items: any[]): any[] =>
    items.map((item) => {
      if (item.children) {
        const filtered = filterItems(item.children);
        return filtered.length > 0 ? { ...item, children: filtered } : null;
      }
      if (item.key === 'logout') return item;
      return hasPermission(item.key) ? item : null;
    }).filter(Boolean);

  const allMenuItems: MenuProps['items'] = [
    {
      type: 'group',
      label: 'DANH MỤC',
      children: [
        { key: '/home', icon: <DashboardOutlined />, label: 'Trang chính' },
        { key: '/mobiles', icon: <MobileOutlined />, label: 'Quản lý điện thoại' },
        { key: '/services', icon: <ToolOutlined />, label: 'Quản lý dịch vụ' },
        { key: '/expenses', icon: <WalletOutlined />, label: 'Quản lý chi phí' },
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
        { key: '/admin/settings', icon: <SettingOutlined />, label: 'Cài đặt hệ thống' },
        { key: 'logout', icon: <LogoutOutlined />, label: 'Đăng xuất' },
      ],
    },
  ];

  const menuItems = useMemo(() => filterItems(allMenuItems as any[]), [permissions]);

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
