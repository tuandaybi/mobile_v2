import React, { useState, useEffect } from 'react';
import { Layout } from 'antd';
import AppSider from './AppSider';
import AppHeader from './AppHeader';
import AppFooter from './AppFooter';
import MobileModal from '../modal/MobileModal';
import ServiceModal from '../modal/ServiceModal';
import SellMobileModal from '../modal/SellMobileModal';
import ExpenseModal from '../modal/ExpenseModal';
import api from '../../../axiosConfig';
import { useNavigate } from 'react-router-dom';

const { Content } = Layout;

interface Props {
  children: React.ReactNode;
}

const MainLayout: React.FC<Props> = ({ children }) => {

  const [collapsed, setCollapsed] = useState(false);
  const navigate = useNavigate();
  //Kiểm tra token hợp lệ
  useEffect(() => {
    api.get('user')
      .then(_ => {
      })
      .catch(err => {
        if (err.response) {
          // Server trả về lỗi
          console.error(
            `Lỗi ${err.response.status}:`,
            err.response.data
          );
          if (err.response.status === 401) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user');
            navigate('/login', { state: { reason: 'TOKEN_EXPIRED' } });
          }
        } else if (err.request) {
          // Không nhận được phản hồi
          console.error('Không thể kết nối tới server:', err.request);
        } else {
          // Lỗi khác (ví dụ config axios)
          console.error('Lỗi khi setup request:', err.message);
        }
      });
  }, [navigate]);


  return (
    <Layout style={{ minHeight: '100vh' }}>
      <AppSider  collapsed={collapsed}  onCollapse={setCollapsed} />
      <Layout>

        <AppHeader collapsed={collapsed} onToggle={() => setCollapsed(!collapsed)} />

        <MobileModal />
        <ServiceModal />
        <SellMobileModal />
        <ExpenseModal />

        <Content style={{background: '#C0C0C0', padding: 40 }}>
          {children}
        </Content>
        
        <AppFooter />
      </Layout>
    </Layout>
  );
};

export default MainLayout;
