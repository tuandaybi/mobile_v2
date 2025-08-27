import React, { Suspense, lazy } from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import ProtectedRoute from '@/router/ProtectedRoute';

const LoginPage = lazy(() => import('@/pages/auth/LoginPage'));
const RegisterPage = lazy(() => import('@/pages/auth/RegisterPage'));
const RenewPage = lazy(() => import('@/pages/auth/RenewPage'));

const DashboardPage = lazy(() => import('@/pages/DashboardPage'));
const Mobiles = lazy(() => import('@/pages/MobilesPage'));
const Services = lazy(() => import('@/pages/ServicesPage'));
const Debt = lazy(() => import('@/pages/DebtsPage'));
const SoldProductsPage = lazy(() => import('@/pages/SoldProductsPage'));
const CheckImeiPage = lazy(() => import('@/pages/CheckImeiPage'));
const ReportProfitPage = lazy(() => import('@/pages/ReportProfitPage'));
const ReportQuantityPage = lazy(() => import('@/pages/ReportQuantityPage'));

// Admin pages
const AdminUsers = lazy(() => import('@/pages/admin/users'));
const AdminCustomers = lazy(() => import('@/pages/admin/customers'));
const AdminDevices = lazy(() => import('@/pages/admin/devices'));
const AdminColors = lazy(() => import('@/pages/admin/colors'));
const AdminStores = lazy(() => import('@/pages/admin/stores'));
const AdminBackups = lazy(() => import('@/pages/admin/backups'));

const Router: React.FC = () => {
  return (
    <BrowserRouter>
      <Suspense fallback={<div>Loading...</div>}>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/dangky" element={<RegisterPage />} />
          <Route path="/giahan" element={<RenewPage />} />

          <Route path="/home" element={<ProtectedRoute><DashboardPage /></ProtectedRoute>} />
          <Route path="/mobiles" element={<ProtectedRoute><Mobiles /></ProtectedRoute>} />
          <Route path="/services" element={<ProtectedRoute><Services /></ProtectedRoute>} />
          <Route path="/debt" element={<ProtectedRoute><Debt /></ProtectedRoute>} />
          <Route path="/sold-products" element={<ProtectedRoute><SoldProductsPage /></ProtectedRoute>} />
          <Route path="/check-imei" element={<ProtectedRoute><CheckImeiPage /></ProtectedRoute>} />
          <Route path="/report-profit" element={<ProtectedRoute><ReportProfitPage /></ProtectedRoute>} />
          <Route path="/report-quantity" element={<ProtectedRoute><ReportQuantityPage /></ProtectedRoute>} />
          <Route path="/admin/users" element={<ProtectedRoute><AdminUsers /></ProtectedRoute>} />
          <Route path="/admin/customers" element={<ProtectedRoute><AdminCustomers /></ProtectedRoute>} />
          <Route path="/admin/stores" element={<ProtectedRoute><AdminStores /></ProtectedRoute>} />
          <Route path="/admin/devices" element={<ProtectedRoute><AdminDevices /></ProtectedRoute>} />
          <Route path="/admin/colors" element={<ProtectedRoute><AdminColors /></ProtectedRoute>} />
          <Route path="/admin/backups" element={<ProtectedRoute><AdminBackups /></ProtectedRoute>} />
          <Route path="*" element={<LoginPage />} />
        </Routes>
      </Suspense>
    </BrowserRouter>
  );
};

export default Router;
