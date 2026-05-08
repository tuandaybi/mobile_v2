import React, { useState, useEffect } from 'react';
import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { Spin } from 'antd';
import api from '@/../axiosConfig';

interface ProtectedRouteProps {
  children: ReactNode;
}

const ProtectedRoute: React.FC<ProtectedRouteProps> = ({ children }) => {
  const token = localStorage.getItem('auth_token');
  const [status, setStatus] = useState<'loading' | 'ok' | 'fail'>(token ? 'loading' : 'fail');

  useEffect(() => {
    if (!token) return;
    api.get('/user').then(() => setStatus('ok')).catch(() => {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
      setStatus('fail');
    });
  }, [token]);

  if (status === 'loading') {
    return <Spin style={{ display: 'flex', justifyContent: 'center', marginTop: '20%' }} size="large" />;
  }
  if (status === 'fail') {
    return <Navigate to="/login" replace />;
  }
  return <>{children}</>;
};

export default ProtectedRoute;
