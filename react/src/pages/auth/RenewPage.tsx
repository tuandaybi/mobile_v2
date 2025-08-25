import React from 'react';
import { Typography } from 'antd';
import RenewForm from '@/components/auth/RenewForm';

const { Title } = Typography;

const RenewPage: React.FC = () => {
  return (
    <div style={{
      height: '100vh',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      background: '#f0f2f5'
    }}>
      <div style={{
        width: 400,
        padding: 24,
        background: '#fff',
        boxShadow: '0 0 10px rgba(0,0,0,0.1)',
        borderRadius: 8
      }}>
        <Title level={3} style={{ textAlign: 'center' }}>Gia hạn</Title>
        <RenewForm />
      </div>
    </div>
  );
};

export default RenewPage;
