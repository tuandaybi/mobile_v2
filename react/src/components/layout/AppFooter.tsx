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
      MyApp ©2025 - Laravel + React
    </Footer>
  );
};

export default AppFooter;
