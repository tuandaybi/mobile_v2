import React from 'react';
import { Layout } from 'antd';

const { Footer } = Layout;

const AppFooter: React.FC = () => {
  return (
    <Footer
      style={{
        textAlign: 'center',
      }}
    >
      Quản lý bán lẻ điện thoại & phụ kiện ©2025
    </Footer>
  );
};

export default AppFooter;
