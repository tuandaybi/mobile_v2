import React, { useState, useEffect } from 'react';
import { Form, Input, Button, message, Checkbox } from 'antd';
import { useNavigate, Link } from 'react-router-dom';
import api from '@/../axiosConfig'; // Import axios config

const LoginForm: React.FC = () => {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);

  const onFinish = async (values: any) => {
    setLoading(true);
    try {
      const res = await api.post('/login', {
        email: values.email,
        password: values.password,
        remember: values.remember,
      });
      localStorage.setItem('auth_token', res.data.user.auth_token);
      localStorage.setItem('user', JSON.stringify(res.data.user));

      message.success('Đăng nhập thành công');
      navigate('/home');
    } catch (err: any) {
      if (err.response) {
        const errorMsg = err.response.data?.message || 'Đăng nhập thất bại';
        message.error(errorMsg);
      } else if (err.request) {
        message.error('Không thể kết nối tới server');
      } else {
        message.error('Có lỗi xảy ra');
      }
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      api.get('/user').then(() => {
        navigate('/home');
      }).catch(() => {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
      });
    }
  }, []);

  return (
    <Form name="loginForm" layout="vertical" onFinish={onFinish} autoComplete="off">
      <Form.Item
        label="Email"
        name="email"
        rules={[
          { required: true, message: 'Vui lòng nhập email!' },
          { type: 'email', message: 'Email không hợp lệ!' },
        ]}
      >
        <Input placeholder="Nhập email" />
      </Form.Item>

      <Form.Item
        label="Mật khẩu"
        name="password"
        rules={[{ required: true, message: 'Vui lòng nhập mật khẩu!' }]}
      >
        <Input.Password placeholder="Nhập mật khẩu" />
      </Form.Item>

      <Form.Item name="remember" valuePropName="checked" style={{ marginBottom: 8 }}>
        <Checkbox>Ghi nhớ đăng nhập</Checkbox>
      </Form.Item>

      <Form.Item>
        <Button type="primary" htmlType="submit" block loading={loading}>
          Đăng nhập
        </Button>
      </Form.Item>

      <Form.Item style={{ textAlign: 'right' }}>
        <span>Bạn chưa có tài khoản? </span>
        <Link to="/dangky">Đăng ký</Link><br />
        <Link to="/giahan">Gia hạn sản phẩm</Link>
      </Form.Item>
    </Form>
  );
};

export default LoginForm;
