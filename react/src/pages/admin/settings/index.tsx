import React, { useState, useEffect } from "react";
import { Card, Form, Input, Switch, Button, Spin, message } from "antd";
import MainLayout from "@/components/layout/MainLayout";
import api from '../../../../axiosConfig';

const AdminSettings: React.FC = () => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    api.get("/admin/settings/telegram")
      .then((res) => {
        form.setFieldsValue({
          telegram_bot_token: res.data.telegram_bot_token,
          telegram_chat_id: res.data.telegram_chat_id,
          telegram_enabled: res.data.telegram_enabled === "1",
        });
      })
      .catch(() => message.error("Không thể tải cài đặt"))
      .finally(() => setLoading(false));
  }, []);

  const handleSave = async () => {
    const values = await form.validateFields();
    setSaving(true);
    try {
      await api.put("/admin/settings/telegram", {
        telegram_bot_token: values.telegram_bot_token || "",
        telegram_chat_id: values.telegram_chat_id || "",
        telegram_enabled: values.telegram_enabled ? "1" : "0",
      });
      message.success("Lưu cài đặt thành công");
    } catch {
      message.error("Lưu cài đặt thất bại");
    } finally {
      setSaving(false);
    }
  };

  return (
    <MainLayout>
      <h2 style={{ marginBottom: 24 }}>Cài đặt hệ thống</h2>
      {loading ? (
        <Spin style={{ display: "flex", justifyContent: "center", marginTop: 100 }} size="large" />
      ) : (
        <Card title="Telegram Notification" style={{ maxWidth: 600 }}>
          <Form form={form} layout="vertical">
            <Form.Item label="Bot Token" name="telegram_bot_token">
              <Input.Password placeholder="Nhập Telegram Bot Token" />
            </Form.Item>
            <Form.Item label="Chat ID" name="telegram_chat_id">
              <Input placeholder="Nhập Telegram Chat ID" />
            </Form.Item>
            <Form.Item label="Bật thông báo" name="telegram_enabled" valuePropName="checked">
              <Switch />
            </Form.Item>
            <Form.Item>
              <Button type="primary" onClick={handleSave} loading={saving}>
                Lưu cài đặt
              </Button>
            </Form.Item>
          </Form>
        </Card>
      )}
    </MainLayout>
  );
};

export default AdminSettings;
