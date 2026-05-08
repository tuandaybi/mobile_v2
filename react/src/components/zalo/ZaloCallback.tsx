import { useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { message } from "antd";
import api from "@/../axiosConfig";

export default function ZaloCallback() {
  const navigate = useNavigate();

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const code = params.get("code");
    const verifier = localStorage.getItem("zalo_code_verifier");

    if (!code || !verifier) return;

    api.post("/zalo/oauth", { code, code_verifier: verifier })
      .then((res) => {
        if (res.data.user?.auth_token) {
          localStorage.setItem("auth_token", res.data.user.auth_token);
          localStorage.setItem("user", JSON.stringify(res.data.user));
          message.success("Đăng nhập Zalo thành công");
          navigate("/home");
        }
      })
      .catch(() => {
        message.error("Đăng nhập Zalo thất bại");
        navigate("/login");
      });
  }, []);

  return <div>Đang xử lý đăng nhập Zalo...</div>;
}
