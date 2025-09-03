import React, { useState, useEffect } from "react";
import { Layout, Dropdown, Space, message, Form } from "antd";
import { useModalStore } from "../../store/modalStore";
import { useNavigate } from 'react-router-dom';
import api from '../../../axiosConfig';
import {
  UserOutlined,
  LogoutOutlined,
  PlusCircleOutlined,
  MobileOutlined,
  MoneyCollectOutlined,
} from "@ant-design/icons";
import ProfileDrawer from '@/components/auth/ProfileDrawer';
import NotificationDropdown from '@/components/notifi/NotificationDropdown';

const { Header } = Layout;

interface AppHeaderProps {
  collapsed: boolean;
  onToggle: () => void;
}

export interface UserInfo {
  id: Number;
  name: string;
  email: string;
  created_at: Date;
  license_expires_at?: string | null; 
  store_name?: string | null;
}

const AppHeader: React.FC<AppHeaderProps> = ({}) => {
  const navigate = useNavigate();
  const [openProfile, setOpenProfile] = useState(false);
  const [form] = Form.useForm();
  const [userInfo, setUserInfo] = useState<UserInfo | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const userString = localStorage.getItem('user');
    if (userString) {
      try {
        const parsedUserInfo = JSON.parse(userString) as UserInfo;
        setUserInfo(parsedUserInfo);
      } catch (error) {
        console.error('Lỗi khi parse dữ liệu từ localStorage:', error);
      }
    } else {
      console.log('Không tìm thấy dữ liệu user trong localStorage.');
    }
  }, []);

  const logout = () => {
    localStorage.clear();
    navigate('login');
  };

  const profileMenuItems = [
    {
      key: "changeProfile",
      icon: <UserOutlined />,
      label: "Thông tin tài khoản",
    },
    {
      type: "divider" as const,
    },
    {
      key: "logout",
      icon: <LogoutOutlined />,
      label: "Đăng xuất",
    },
  ];

  const showModal = [
    {
      key: "addMobile",
      icon: <MobileOutlined />,
      label: "Thêm điện thoại",
    },
    {
      key: "addService",
      icon: <MoneyCollectOutlined />,
      label: "Thêm dịch vụ",
    },
  ];

  const { mobile, service } = useModalStore();

  const handleAddMenuClick = ({ key }: { key: string }) => {
    if (key === "addMobile") {
      mobile.open(false);
    } else if (key === "addService") {
      service.open(false);
    }
  };

  const handleProfileMenuClick = ({ key }: { key: string }) => {
    if (key === "changeProfile") {
      setOpenProfile(true);
    } else if (key === "logout") {
      logout();
    }
  };

  const handleSaveProfile = async (values: any) => {
  let { name, email, currentPass = '', newPass = '', renewPass = '' } = values;

  // Trim để tránh case có khoảng trắng
  currentPass = String(currentPass).trim();
  newPass     = String(newPass).trim();
  renewPass   = String(renewPass).trim();

  const changing = !!(currentPass || newPass || renewPass);

  // Build payload
  const payload: any = { name, email };

  if (changing) {
    if (!currentPass || !newPass || !renewPass) {
      message.error('Vui lòng điền đầy đủ mật khẩu hiện tại, mật khẩu mới và xác nhận mật khẩu mới.');
      return false;
    }
    if (newPass !== renewPass) {
      message.error('Mật khẩu mới và xác nhận mật khẩu không khớp.');
      return false;
    }
    payload.currentPass = currentPass;
    payload.newPass     = newPass;
    payload.renewPass   = renewPass; // ⬅️ QUAN TRỌNG
  }

  setLoading(true);
  try {
    const res = await api.patch('/user', payload); // hoặc '/me' tùy route backend
    message.success(changing ? 'Cập nhật thông tin và mật khẩu thành công!' : 'Cập nhật thông tin thành công!');
    localStorage.setItem('user', JSON.stringify(res.data.user));
    setUserInfo?.(res.data.user);
    setOpenProfile(false);
    return true;
  } catch (err: any) {
    const errors = err.response?.data?.errors;
    if (errors) {
      // map lỗi từ backend vào form
      form?.setFields(Object.keys(errors).map((k) => ({ name: k, errors: errors[k] })));
    }
    message.error(err.response?.data?.message || 'Cập nhật thất bại');
    return false;
  } finally {
    setLoading(false);
  }
};

  return (
    <>
      <Header
        style={{
          padding: "0 40px",
          display: "flex",
          alignItems: "center",
          justifyContent: "space-between",
          color: "#fff",
        }}
      >
        <div style={{ display: "flex", alignItems: "center", gap: 30 }}>
          <Dropdown
            menu={{
              items: showModal,
              onClick: handleAddMenuClick,
            }}
            placement="bottomLeft"
          >
            <Space style={{ cursor: "pointer" }}>
              <PlusCircleOutlined />
              <span>Thêm mới</span>
            </Space>
          </Dropdown>
        </div>

        <div style={{ display: "flex", alignItems: "right", gap: 30, justifyContent: "space-end" }}>
          <div>
            <Dropdown
                menu={{
                  items: profileMenuItems,
                  onClick: handleProfileMenuClick,
                }}
              placement="bottom"
            >
              <Space style={{ cursor: "pointer" }}>
                <UserOutlined />
                <span id="userName">{userInfo ? userInfo.name : 'Khách'}</span>
              </Space>
            </Dropdown>
          </div>
          <div>
            <NotificationDropdown />
          </div>
        </div>
      </Header>
      <ProfileDrawer
        open={openProfile}
        onClose={() => setOpenProfile(false)}
        userInfo={userInfo}
        form={form}
        onFinish={handleSaveProfile}
        loading={loading}
      />
    </>
  );
};

export default AppHeader;