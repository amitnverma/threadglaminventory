import { useEffect, useState } from 'react';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';
import api from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import { formatCurrency } from '@/lib/utils';

const COLORS = ['#c026d3', '#94a3b8', '#f59e0b', '#10b981', '#ef4444'];

export default function ReportsIndex() {
  const [eventsSummary, setEventsSummary] = useState([]);
  const [monthly, setMonthly] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([
      api.get('/reports/events-summary'),
      api.get('/reports/profit-loss'),
    ]).then(([events, monthly]) => {
      setEventsSummary(events.data);
      setMonthly(monthly.data);
    }).finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="animate-pulse h-64 bg-gray-200 rounded-xl" />;

  const pieData = monthly ? [
    { name: 'Revenue', value: monthly.revenue },
    { name: 'Expenses', value: monthly.expenses },
  ] : [];

  return (
    <div>
      <PageHeader title="Reports" subtitle="Profit & loss analysis" />

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div className="card p-5 text-center">
          <p className="text-sm text-gray-500">This Month Revenue</p>
          <p className="text-2xl font-bold text-green-600">{formatCurrency(monthly?.revenue)}</p>
        </div>
        <div className="card p-5 text-center">
          <p className="text-sm text-gray-500">This Month Expenses</p>
          <p className="text-2xl font-bold text-red-600">{formatCurrency(monthly?.expenses)}</p>
        </div>
        <div className="card p-5 text-center">
          <p className="text-sm text-gray-500">This Month Profit</p>
          <p className={`text-2xl font-bold ${monthly?.profit >= 0 ? 'text-brand-600' : 'text-red-600'}`}>{formatCurrency(monthly?.profit)}</p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div className="card p-5">
          <h3 className="font-semibold mb-4">Revenue vs Expenses</h3>
          <ResponsiveContainer width="100%" height={250}>
            <PieChart>
              <Pie data={pieData} cx="50%" cy="50%" outerRadius={80} dataKey="value" label={({ name, value }) => `${name}: ${formatCurrency(value)}`}>
                {pieData.map((_, i) => <Cell key={i} fill={COLORS[i]} />)}
              </Pie>
              <Tooltip formatter={(v) => formatCurrency(v)} />
            </PieChart>
          </ResponsiveContainer>
        </div>

        <div className="card p-5">
          <h3 className="font-semibold mb-4">Event Profitability</h3>
          <ResponsiveContainer width="100%" height={250}>
            <BarChart data={eventsSummary.slice(0, 8)}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
              <XAxis dataKey="title" tick={{ fontSize: 10 }} interval={0} angle={-20} textAnchor="end" height={60} />
              <YAxis tick={{ fontSize: 12 }} />
              <Tooltip formatter={(v) => formatCurrency(v)} />
              <Bar dataKey="profit_loss.profit" fill="#c026d3" name="Profit" radius={[4,4,0,0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>

      <div className="card overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="text-left p-3">Event</th><th className="text-left p-3">Customer</th>
            <th className="text-left p-3">Status</th><th className="text-right p-3">Revenue</th>
            <th className="text-right p-3">Expenses</th><th className="text-right p-3">Profit</th>
          </tr></thead>
          <tbody>
            {eventsSummary.map((ev) => (
              <tr key={ev.id} className="border-t hover:bg-gray-50">
                <td className="p-3 font-medium">{ev.title}</td>
                <td className="p-3">{ev.customer_name}</td>
                <td className="p-3 capitalize">{ev.status}</td>
                <td className="p-3 text-right text-green-600">{formatCurrency(ev.profit_loss?.revenue)}</td>
                <td className="p-3 text-right text-red-600">{formatCurrency(ev.profit_loss?.expenses)}</td>
                <td className={`p-3 text-right font-medium ${ev.profit_loss?.profit >= 0 ? 'text-brand-600' : 'text-red-600'}`}>
                  {formatCurrency(ev.profit_loss?.profit)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
