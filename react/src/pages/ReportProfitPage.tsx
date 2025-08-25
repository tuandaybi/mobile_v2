import React, { useState } from "react";
import MainLayout from "../components/layout/MainLayout";
import { Row, Col, Card, Select, Typography } from "antd";
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from "recharts";

const { Text } = Typography;
const { Option } = Select;

const COLORS = [
  "#8884d8",
  "#82ca9d",
  "#ffc658",
  "#ff7300",
  "#0088FE",
  "#00C49F",
  "#FFBB28",
  "#FF8042",
  "#a83279",
  "#3299a8",
  "#a86e32",
  "#673ab7",
];

type ProfitData = {
  year: number;
  month: string;
  day: number;
  revenue: number;
  profit: number;
};

const mockPhoneData: ProfitData[] = [];
const mockServiceData: ProfitData[] = [];

for (let m = 1; m <= 12; m++) {
  for (let d = 1; d <= 28; d++) {
    const phoneRevenue = Math.floor(150000 + Math.random() * 200000); // 150k-350k
    const phoneProfit = Math.floor(phoneRevenue * (0.10 + Math.random() * 0.05)); // 10%-15%

    mockPhoneData.push({
      year: 2025,
      month: m.toString(),
      day: d,
      revenue: phoneRevenue,
      profit: phoneProfit,
    });

    const serviceRevenue = Math.floor(80000 + Math.random() * 100000); // 80k-180k
    const serviceProfit = Math.floor(serviceRevenue * (0.5 + Math.random() * 0.1)); // 50%-60%

    mockServiceData.push({
      year: 2025,
      month: m.toString(),
      day: d,
      revenue: serviceRevenue,
      profit: serviceProfit,
    });
  }
}



const getDaysInMonth = () => Array.from({ length: 31 }, (_, i) => i + 1);

const getYearOptions = () => {
  const currentYear = new Date().getFullYear();
  return [currentYear - 1, currentYear];
};

const ProfitReportPage: React.FC = () => {
  const currentYear = new Date().getFullYear();
  const currentMonth = (new Date().getMonth() + 1).toString();

  const [years, setYears] = useState<number[]>([currentYear]);
  const [months, setMonths] = useState<string[]>([currentMonth]);

  // Filter dữ liệu điện thoại
  const filteredPhoneData = mockPhoneData.filter(
    (d) => years.includes(d.year) && months.includes(d.month)
  );
  // Filter dữ liệu dịch vụ
  const filteredServiceData = mockServiceData.filter(
    (d) => years.includes(d.year) && months.includes(d.month)
  );

  const days = getDaysInMonth();

  // Tạo data chart chung cho điện thoại hoặc dịch vụ
  const createChartData = (data: typeof mockPhoneData) => {
    return days.map((day) => {
      const obj: any = { name: day.toString() };
      months.forEach((m) => {
        const dayData = data.find(
          (d) => d.month === m && d.day === day && years.includes(d.year)
        );
        obj[`m${m}`] = dayData ? dayData.revenue : null;
      });
      return obj;
    });
  };

  const phoneChartData = createChartData(filteredPhoneData);
  const serviceChartData = createChartData(filteredServiceData);

  const phoneRevenueSum = filteredPhoneData.reduce((sum, d) => sum + d.revenue, 0);
  const phoneProfitSum = filteredPhoneData.reduce((sum, d) => sum + d.profit, 0);
  const phoneMargin = phoneRevenueSum ? (phoneProfitSum / phoneRevenueSum) * 100 : 0;

  const serviceRevenueSum = filteredServiceData.reduce((sum, d) => sum + d.revenue, 0);
  const serviceProfitSum = filteredServiceData.reduce((sum, d) => sum + d.profit, 0);
  const serviceMargin = serviceRevenueSum ? (serviceProfitSum / serviceRevenueSum) * 100 : 0;

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
                  onChange={(vals) => setMonths(vals as string[])}
                >
                  {[...Array(12).keys()].map((m) => (
                    <Option key={m + 1} value={`${m + 1}`}>
                      Tháng {m + 1}
                    </Option>
                  ))}
                </Select>
              </Col>
            </Row>

            {/* Thông tin điện thoại */}
            <Row style={{ marginBottom: 8 }}>
              <Text strong style={{ fontSize: 16 }}>
                Tổng tiền đã bán máy:{" "}
              </Text>
              <Text style={{ fontSize: 16 }}>{phoneRevenueSum.toLocaleString()} VNĐ</Text>
            </Row>
            <Row style={{ marginBottom: 8 }}>
              <Text strong style={{ fontSize: 16 }}>Lợi nhuận: </Text>
              <Text style={{ fontSize: 16 }}>{phoneProfitSum.toLocaleString()} VNĐ</Text>
            </Row>
            <Row style={{ marginBottom: 16 }}>
              <Text strong style={{ fontSize: 16 }}>Tỉ suất lợi nhuận: </Text>
              <Text style={{ fontSize: 16 }}>{phoneMargin.toFixed(2)}%</Text>
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
                  <Tooltip />
                  {months.map((m, idx) => (
                    <Line
                      key={m}
                      type="monotone"
                      dataKey={`m${m}`}
                      stroke={COLORS[idx % COLORS.length]}
                      name={`Tháng ${m}`}
                      activeDot={{ r: 6 }}
                      connectNulls={false}
                    />
                  ))}
                </LineChart>
              </ResponsiveContainer>
            </div>

            {/* Thông tin dịch vụ */}
            <Row style={{ marginBottom: 8 }}>
              <Text strong style={{ fontSize: 16 }}>
                Tổng tiền dịch vụ:{" "}
              </Text>
              <Text style={{ fontSize: 16 }}>{serviceRevenueSum.toLocaleString()} VNĐ</Text>
            </Row>
            <Row style={{ marginBottom: 8 }}>
              <Text strong style={{ fontSize: 16 }}>Lợi nhuận: </Text>
              <Text style={{ fontSize: 16 }}>{serviceProfitSum.toLocaleString()} VNĐ</Text>
            </Row>
            <Row style={{ marginBottom: 16 }}>
              <Text strong style={{ fontSize: 16 }}>Tỉ suất lợi nhuận: </Text>
              <Text style={{ fontSize: 16 }}>{serviceMargin.toFixed(2)}%</Text>
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
                  <Tooltip />
                  {months.map((m, idx) => (
                    <Line
                      key={m}
                      type="monotone"
                      dataKey={`m${m}`}
                      stroke={COLORS[(idx + months.length) % COLORS.length]} // tránh trùng màu điện thoại
                      name={`Tháng ${m}`}
                      activeDot={{ r: 6 }}
                      connectNulls={false}
                    />
                  ))}
                </LineChart>
              </ResponsiveContainer>
            </div>
          </Card>
        </Col>
      </Row>
    </MainLayout>
  );
};

export default ProfitReportPage;
