import React, { useEffect, useMemo, useState } from "react";
import MainLayout from "../components/layout/MainLayout";
import { Row, Col, Card, Select, Typography, message, Spin } from "antd";
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip as RTooltip,
  ResponsiveContainer,
} from "recharts";
import api from "../../axiosConfig";

const { Text } = Typography;
const { Option } = Select;

const COLORS = [
  "#8884d8", "#82ca9d", "#ffc658", "#ff7300", "#0088FE", "#00C49F",
  "#FFBB28", "#FF8042", "#a83279", "#3299a8", "#a86e32", "#673ab7",
];

type DailyAgg = {
  year: number;
  month: number;  // API trả number
  day: number;
  revenue: number;
  profit: number;
};

type ProfitDailyResponse = {
  phone: DailyAgg[];
  service: DailyAgg[];
};

const getDaysInMonth = () => Array.from({ length: 31 }, (_, i) => i + 1);
const getYearOptions = () => {
  const currentYear = new Date().getFullYear();
  return [...Array(currentYear - 2020).keys()].map((y) => y + 2021);
};

const ProfitReportPage: React.FC = () => {
  const currentYear = new Date().getFullYear();
  const currentMonth = new Date().getMonth() + 1;

  const [years, setYears] = useState<number[]>([currentYear]);
  const [months, setMonths] = useState<number[]>([currentMonth]);

  const [loading, setLoading] = useState(false);
  const [phoneData, setPhoneData] = useState<DailyAgg[]>([]);
  const [serviceData, setServiceData] = useState<DailyAgg[]>([]);

  const loadData = async () => {
    setLoading(true);
    try {
      const params = {
        years: years.join(","),
        months: months.join(","),
      };
      const res = await api.get<ProfitDailyResponse>("/reports/profit-daily", { params });
      setPhoneData(res.data.phone || []);
      setServiceData(res.data.service || []);
    } catch (err: any) {
      console.error(err);
      message.error("Không tải được dữ liệu báo cáo");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [JSON.stringify(years), JSON.stringify(months)]);

  const days = getDaysInMonth();

  // Utility: Build chart data theo ngày và theo nhiều tháng (series m{month})
  const buildChartData = (src: DailyAgg[], valueKey: "revenue" | "profit" = "revenue") => {
    // group theo {month -> {day -> sum}}
    const map = new Map<number, Map<number, number>>();
    for (const row of src) {
      if (!years.includes(row.year)) continue; // lọc phòng hờ
      if (!months.includes(row.month)) continue;
      if (!map.has(row.month)) map.set(row.month, new Map());
      const dmap = map.get(row.month)!;
      dmap.set(row.day, (dmap.get(row.day) || 0) + (row[valueKey] || 0));
    }

    return days.map((day) => {
      const obj: any = { name: String(day) };
      months.forEach((m) => {
        const val = map.get(m)?.get(day) ?? null;
        obj[`m${m}`] = val;
      });
      return obj;
    });
  };

  // Tổng hợp số liệu
  const phoneRevenueSum = useMemo(
    () => phoneData.reduce((s, r) => s + (r.revenue || 0), 0),
    [phoneData]
  );
  const phoneProfitSum = useMemo(
    () => phoneData.reduce((s, r) => s + (r.profit || 0), 0),
    [phoneData]
  );
  const phoneMargin = phoneRevenueSum ? (phoneProfitSum / phoneRevenueSum) * 100 : 0;

  const serviceRevenueSum = useMemo(
    () => serviceData.reduce((s, r) => s + (r.revenue || 0), 0),
    [serviceData]
  );
  const serviceProfitSum = useMemo(
    () => serviceData.reduce((s, r) => s + (r.profit || 0), 0),
    [serviceData]
  );
  const serviceMargin = serviceRevenueSum ? (serviceProfitSum / serviceRevenueSum) * 100 : 0;

  const phoneChartData = buildChartData(phoneData, "revenue");
  const serviceChartData = buildChartData(serviceData, "revenue");

  return (
    <MainLayout>
      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Card title="📊 Báo cáo lợi nhuận chi tiết theo ngày - So sánh nhiều tháng">
            <Row gutter={16} style={{ marginBottom: 16 }}>
              <Col span={6}>
                <label>Năm:</label>
                <Select
                  mode="multiple"
                  allowClear
                  placeholder="Chọn năm"
                  style={{ width: "100%" }}
                  value={years}
                  onChange={(vals) => setYears(vals as number[])}
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
                  allowClear
                  placeholder="Chọn tháng"
                  style={{ width: "100%" }}
                  value={months}
                  onChange={(vals) => setMonths(vals as number[])}
                >
                  {[...Array(12).keys()].map((m) => (
                    <Option key={m + 1} value={m + 1}>
                      Tháng {m + 1}
                    </Option>
                  ))}
                </Select>
              </Col>
            </Row>

            <Spin spinning={loading}>
              {/* Điện thoại */}
              <Row style={{ marginBottom: 8 }}>
                <Text strong style={{ fontSize: 16 }}>Tổng tiền đã bán máy: </Text>
                <Text style={{ fontSize: 16, marginLeft: 8 }}>{phoneRevenueSum.toLocaleString()} VNĐ</Text>
              </Row>
              <Row style={{ marginBottom: 8 }}>
                <Text strong style={{ fontSize: 16 }}>Lợi nhuận: </Text>
                <Text style={{ fontSize: 16, marginLeft: 8 }}>{phoneProfitSum.toLocaleString()} VNĐ</Text>
              </Row>
              <Row style={{ marginBottom: 16 }}>
                <Text strong style={{ fontSize: 16 }}>Tỉ suất lợi nhuận: </Text>
                <Text style={{ fontSize: 16, marginLeft: 8 }}>{phoneMargin.toFixed(2)}%</Text>
              </Row>

              <div style={{ width: "100%", height: 300, marginBottom: 40 }}>
                <ResponsiveContainer>
                  <LineChart data={phoneChartData}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis
                      dataKey="name"
                      label={{ value: "Ngày trong tháng", position: "insideBottomRight", offset: -5 }}
                    />
                    <YAxis />
                    <RTooltip />
                    {months.map((m, idx) => (
                      <Line
                        key={m}
                        type="monotone"
                        dataKey={`m${m}`}
                        stroke={COLORS[idx % COLORS.length]}
                        name={`Tháng ${m}`}
                        activeDot={{ r: 6 }}
                        connectNulls={true}
                      />
                    ))}
                  </LineChart>
                </ResponsiveContainer>
              </div>

              {/* Dịch vụ */}
              <Row style={{ marginBottom: 8 }}>
                <Text strong style={{ fontSize: 16 }}>Tổng tiền dịch vụ: </Text>
                <Text style={{ fontSize: 16, marginLeft: 8 }}>{serviceRevenueSum.toLocaleString()} VNĐ</Text>
              </Row>
              <Row style={{ marginBottom: 8 }}>
                <Text strong style={{ fontSize: 16 }}>Lợi nhuận: </Text>
                <Text style={{ fontSize: 16, marginLeft: 8 }}>{serviceProfitSum.toLocaleString()} VNĐ</Text>
              </Row>
              <Row style={{ marginBottom: 16 }}>
                <Text strong style={{ fontSize: 16 }}>Tỉ suất lợi nhuận: </Text>
                <Text style={{ fontSize: 16, marginLeft: 8 }}>{serviceMargin.toFixed(2)}%</Text>
              </Row>

              <div style={{ width: "100%", height: 300 }}>
                <ResponsiveContainer>
                  <LineChart data={serviceChartData}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis
                      dataKey="name"
                      label={{ value: "Ngày trong tháng", position: "insideBottomRight", offset: -5 }}
                    />
                    <YAxis />
                    <RTooltip />
                    {months.map((m, idx) => (
                      <Line
                        key={m}
                        type="monotone"
                        dataKey={`m${m}`}
                        stroke={COLORS[(idx + months.length) % COLORS.length]}
                        name={`Tháng ${m}`}
                        activeDot={{ r: 6 }}
                        connectNulls={true}
                      />
                    ))}
                  </LineChart>
                </ResponsiveContainer>
              </div>
            </Spin>
          </Card>
        </Col>
      </Row>
    </MainLayout>
  );
};

export default ProfitReportPage;
