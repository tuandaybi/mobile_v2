import React, {useState} from 'react';
import { Form, Input, Button, Checkbox, message } from 'antd';
import { Link } from 'react-router-dom';
import api from '../../../axiosConfig'

const RegisterForm: React.FC = () => {
  const [form] = Form.useForm();

  const [loading, setLoading] = useState(false);

  const onFinish = async (values: any) => {
    const { name, email, password, password_confirmation } = values;
    setLoading(true);
    try {
      const res = await api.post('register', { name, email, password, password_confirmation }, { suppressToast: true });
      message.success('Tạo tài khoản thành công!');
      console.log('Thông tin tài khoản:', res.data);
      form.resetFields();
    } catch (err: any) {
      if (err.response) {
        const errorMsg = err.response?.data?.message || 'Tạo tài khoản thất bại';
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

  return (
    <Form
      form={form}
      layout="vertical"
      onFinish={onFinish}
      autoComplete="off"
    >
      <Form.Item
        label="Họ tên"
        name="name"
        rules={[{ required: true, message: 'Vui lòng nhập họ tên!' }]}
      >
        <Input placeholder="Nhập họ tên" />
      </Form.Item>

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
        hasFeedback
      >
        <Input.Password placeholder="Nhập mật khẩu" autoComplete="new-password" />
      </Form.Item>

      <Form.Item
        label="Nhập lại mật khẩu"
        name="password_confirmation"
        dependencies={['password']}
        hasFeedback
        rules={[
          { required: true, message: 'Vui lòng nhập lại mật khẩu!' },
          ({ getFieldValue }) => ({
            validator(_, value) {
              if (!value || getFieldValue('password') === value) {
                return Promise.resolve();
              }
              return Promise.reject(new Error('Mật khẩu không khớp!'));
            },
          }),
        ]}
      >
        <Input.Password placeholder="Nhập lại mật khẩu" autoComplete="new-password"/>
      </Form.Item>

      <Form.Item
        name="agree"
        valuePropName="checked"
        rules={[
          {
            validator: (_, value) =>
              value
                ? Promise.resolve()
                : Promise.reject(new Error('Bạn phải đồng ý với điều khoản!')),
          },
        ]}
      >
        <Checkbox>
          Tôi đồng ý với <a href="#">điều khoản sử dụng</a>
        </Checkbox>
      </Form.Item>

      <Form.Item>
        <Button type="primary" htmlType="submit" loading={loading} block>
          Đăng ký
        </Button>
      </Form.Item>

      <Form.Item style={{ textAlign: 'right' }}>
        Đã có tài khoản? <Link to="/login">Đăng nhập</Link>
      </Form.Item>
    </Form>
  );
};

export default RegisterForm;
