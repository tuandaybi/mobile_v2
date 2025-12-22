import { useEffect } from "react";
import axios from "../../../axiosConfig";

export default function ZaloCallback() {
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const code = params.get("code");
    const state = params.get("state");
    const verifier = localStorage.getItem("zalo_code_verifier");

    if (!code || !verifier) return;

    axios.post("/api/zalo/oauth", {
      code,
      code_verifier: verifier,
    }).then(res => {
      console.log("LOGIN OK", res.data);
    });
  }, []);

  return <div>Đang xử lý đăng nhập Zalo...</div>;
}
