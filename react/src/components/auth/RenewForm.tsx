import React, { useState } from 'react';
import { Form, Input, Button, message } from 'antd';
import { Link } from 'react-router-dom';
import api from '../../../axiosConfig'; // Import axios config

const RenewForm: React.FC = () => {
  const [loading, setLoading] = useState(false);

  const onFinish = async (values: any) => {
    setLoading(true);
    try {
      const res = await api.post('redeem', {
        code: values.token_key,
      });
      message.success(res.data.message);
    } catch (err: any) {
      if (err.response) {
        const errorMsg = err.response.data?.message || 'Gia hạn thất bại';
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
      layout="vertical"
      onFinish={onFinish}
      autoComplete="off"
    >
      <Form.Item
        label="Mã gia hạn"
        name="token_key"
        rules={[{ required: true, message: 'Vui lòng nhập mã gia hạn!' }]}
      >
        <Input.TextArea
          placeholder="Nhập mã gia hạn"
          autoSize={{ minRows: 3 }}
        />
      </Form.Item>

      <Form.Item>
        <Button type="primary" htmlType="submit" loading={loading} block>
          Gia hạn
        </Button>
      </Form.Item>

      <Form.Item style={{ textAlign: 'right' }}>
        Quay về trang <Link to="/dangnhap">Đăng nhập</Link>
    </Form.Item>
    </Form>
  );
};

export default RenewForm;
