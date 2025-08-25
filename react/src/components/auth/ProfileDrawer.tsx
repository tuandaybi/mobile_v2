import React from 'react';
import { Drawer, Form, Input, Button, Divider } from 'antd';
import { type UserInfo } from '@/components/layout/AppHeader';
// ✅ đúng cách
import { format } from 'date-fns';

interface ProfileDrawerProps {
  open: boolean;
  onClose: () => void;
  userInfo: UserInfo | null;
  form: any;
  onFinish: (values: any) => Promise<boolean>;
  loading: boolean;
}

const ProfileDrawer: React.FC<ProfileDrawerProps> = ({
  open,
  onClose,
  userInfo,
  form,
  onFinish,
  loading,
}) => {
  // helper nhỏ tính ngày còn lại (tính tới cuối ngày hết hạn)
  const renderExpire = () => {
    const s = userInfo?.license_expires_at;
    if (!s) return 'Chưa có thông tin';
    const iso = s.includes('T') ? s : s.replace(' ', 'T');
    const d = new Date(iso);
    if (isNaN(d.getTime())) return 'Chưa có thông tin';
    const eod = new Date(d);
    eod.setHours(23, 59, 59, 999);
    const days = Math.max(0, Math.ceil((eod.getTime() - Date.now()) / 86400000));
    return `${format(d, 'dd/MM/yyyy')} — Còn ${days} ngày`;
  };

  return (
    <Drawer title="Thông tin tài khoản" open={open} onClose={onClose} width={400}>
      <Form
        layout="vertical"
        form={form}
        onFinish={onFinish}
        initialValues={{
          name: userInfo?.name ?? '',
          email: userInfo?.email ?? '',
          createdAt: userInfo ? format(new Date(userInfo.created_at), 'dd/MM/yyyy') : '',
        }}
      >
        <Form.Item
          name="name"
          label="Họ và tên"
          rules={[{ required: true, message: 'Vui lòng nhập họ tên' }]}
        >
          <Input />
        </Form.Item>

        <Form.Item
          name="email"
          label="Email"
          rules={[{ required: true, type: 'email', message: 'Email không hợp lệ' }]}
        >
          <Input />
        </Form.Item>

        <Form.Item label="Ngày tạo tài khoản" name="createdAt">
          <Input disabled />
        </Form.Item>

        {/* ✅ chỉ hiển thị, không để name */}
        <Form.Item label="Hạn sử dụng phần mềm">
          <div className="ant-form-text">{renderExpire()}</div>
        </Form.Item>

        <Divider />
        <h4>Đổi mật khẩu</h4>

        <Form.Item
          name="currentPass"
          label="Mật khẩu hiện tại"
          rules={[{ min: 8, message: 'Mật khẩu ít nhất 8 ký tự' }]}
        >
          <Input.Password autoComplete="new-password" />
        </Form.Item>

        <Form.Item
          name="newPass"
          label="Mật khẩu mới"
          rules={[{ min: 8, message: 'Mật khẩu ít nhất 8 ký tự' }]}
        >
          <Input.Password autoComplete="new-password" />
        </Form.Item>

        <Form.Item
          name="renewPass"
          label="Xác nhận mật khẩu mới"
          dependencies={['newPass']}
          rules={[
            ({ getFieldValue }) => ({
              validator(_, value) {
                if (!value || getFieldValue('newPass') === value) return Promise.resolve();
                return Promise.reject(new Error('Mật khẩu không khớp'));
              },
            }),
          ]}
        >
          <Input.Password autoComplete="new-password" />
        </Form.Item>

        <Button type="primary" block htmlType="submit" loading={loading}>
          Lưu thay đổi
        </Button>
      </Form>
    </Drawer>
  );
};

export default ProfileDrawer;
