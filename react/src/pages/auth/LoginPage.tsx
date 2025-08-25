import React from 'react';
import { Typography } from 'antd';
import LoginForm from '@/components/auth/LoginForm';

const { Title } = Typography;

const LoginPage: React.FC = () => {
  return (
    <div
      style={{
        minHeight: '100vh',
        display: 'flex',
        justifyContent: 'center',
        alignItems: 'center',
        background: '#f0f2f5',
      }}
    >
      <div style={{ width: 400, padding: 24, background: '#fff', borderRadius: 8, boxShadow: '0 0 8px #ccc' }}>
        <Title level={3} style={{ textAlign: 'center' }}>TMobile</Title>
        <LoginForm />
      </div>
    </div>
  );
};

export default LoginPage;
