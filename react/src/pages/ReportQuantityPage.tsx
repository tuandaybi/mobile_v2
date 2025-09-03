import React, { useEffect, useMemo, useState } from "react";
import MainLayout from "../components/layout/MainLayout";
import { Row, Col, Card, Select, message, Spin, Statistic } from "antd";
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from "recharts";
import api from "../../axiosConfig";

const { Option } = Select;

const getYearOptions = () => {
  const currentYear = new Date().getFullYear();
  return [...Array(currentYear - 2020).keys()].map((y) => y + 2021);
};
const getMonthOptions = () => [...Array(12).keys()].map((m) => (m + 1).toString());

type ModelQty = { model: string; quantity: number };

const ReportProductionPage: React.FC<{}> = () => {
  const currentYear = new Date().getFullYear();
  const currentMonth = (new Date().getMonth() + 1).toString();

  const [years, setYears] = useState<number[]>([currentYear]);
  const [months, setMonths] = useState<string[]>([currentMonth]);
  const [data, setData] = useState<ModelQty[]>([]);
  const [loading, setLoading] = useState(false);

  const totalQty = useMemo(
    () => data.reduce((acc, cur) => acc + Number(cur?.quantity ?? 0), 0),
    [data]
  );

  const load = async () => {
    setLoading(true);
    try {
      const params = {
        years: years.join(","),   // "2025,2024"
        months: months.join(","), // "8,7"
      };
      const res = await api.get<ModelQty[]>("/reports/sales-models", { params });
      setData((res.data as any) || []);
    } catch (e) {
      console.error(e);
      message.error("Không tải được báo cáo sản lượng model");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [JSON.stringify(years), JSON.stringify(months)]);

  return (
    <MainLayout>
      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Card title="📦 Báo cáo sản lượng máy bán">
            <Row gutter={16} style={{ marginBottom: 24 }}>
              <Col span={6}>
                <label>Năm:</label>
                <Select
                  mode="multiple"
                  value={years}
                  onChange={(val) => setYears(val as number[])}
                  style={{ width: "100%" }}
                  placeholder="Chọn năm"
                  allowClear
                >
                  {getYearOptions().map((y) => (
                    <Option key={y} value={y}>
                      {y}
                    </Option>
                  ))}
                </Select>
              </Col>

              <Col span={6}>
                <label>Tháng:</label>
                <Select
                  mode="multiple"
                  value={months}
                  onChange={(val) => setMonths(val as string[])}
                  style={{ width: "100%" }}
                  placeholder="Chọn tháng"
                  allowClear
                >
                  {getMonthOptions().map((m) => (
                    <Option key={m} value={m}>
                      Tháng {m}
                    </Option>
                  ))}
                </Select>
              </Col>
            </Row>
            <Row>
              <Col span={24} style={{ display: 'flex', justifyContent: 'center' }}>
                <div>
                  <Statistic
                    title="Tổng số lượng đã bán"
                    value={totalQty}
                    suffix="máy"
                  />
                </div>
              </Col>
            </Row>

            <Spin spinning={loading}>
              <div style={{ width: "100%", height: 400 }}>
                <ResponsiveContainer>
                  <BarChart
                    data={data}
                    margin={{ top: 20, right: 30, left: 20, bottom: 5 }}
                  >
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="model" />
                    <YAxis allowDecimals={false} />
                    <Tooltip />
                    <Bar dataKey="quantity" fill="#1890ff" />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </Spin>
          </Card>
        </Col>
      </Row>
    </MainLayout>
  );
};

export default ReportProductionPage;
