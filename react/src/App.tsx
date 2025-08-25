import React from 'react';
import { ConfigProvider } from 'antd';
import Router from './router';

const App: React.FC = () => {
  return (
    <ConfigProvider>
      <Router />
    </ConfigProvider>
  );
};

export default App;
