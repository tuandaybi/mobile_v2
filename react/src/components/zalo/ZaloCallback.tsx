import { useEffect } from "react";
import { useSearchParams } from "react-router-dom";

export default function ZaloCallback() {
  const [params] = useSearchParams();

  useEffect(() => {
    const code = params.get("code");
    const state = params.get("state");

    console.log("Zalo code:", code);
    console.log("State:", state);

    // TODO: gửi code về backend để đổi access_token
  }, []);

  return (
    <div style={{ padding: 20 }}>
      <h3>Đang xử lý đăng nhập Zalo...</h3>
    </div>
  );
}
