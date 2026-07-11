import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Package, Calendar, TrendingUp, AlertTriangle, ArrowRight } from 'lucide-react';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from 'recharts';
import api from '@/lib/api';
import StatCard from '@/components/ui/StatCard';
import PageHeader from '@/components/ui/PageHeader';
import { formatCurrency, formatDate } from '@/lib/utils';
import { StatusBadge } from '@/components/ui/PageHeader';
import { EVENT_STATUSES } from '@/lib/utils';

export default function Dashboard() {
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/reports/dashboard').then(({ data }) => setStats(data)).finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="animate-pulse space-y-4">{[1,2,3,4].map(i => <div key={i} className="h-24 bg-gray-200 rounded-xl" />)}</div>;

  return (
    <div>
      <PageHeader title="Dashboard" subtitle="Overview of your event management business" />

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <StatCard title="Inventory Items" value={stats.inventory_count} subtitle={`${stats.inventory_qty} units in stock`} icon={Package} />
        <StatCard title="Active Events" value={stats.active_events} icon={Calendar} color="blue" />
        <StatCard title="Total Revenue" value={formatCurrency(stats.total_revenue)} icon={TrendingUp} color="green" />
        <StatCard title="Low Stock Items" value={stats.low_stock_count} icon={AlertTriangle} color={stats.low_stock_count > 0 ? 'orange' : 'brand'} />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="card p-5">
          <h3 className="font-semibold mb-4">Revenue vs Expenses (6 months)</h3>
          <ResponsiveContainer width="100%" height={250}>
            <BarChart data={stats.monthly_chart}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
              <XAxis dataKey="month" tick={{ fontSize: 12 }} />
              <YAxis tick={{ fontSize: 12 }} />
              <Tooltip formatter={(v) => formatCurrency(v)} />
              <Legend />
              <Bar dataKey="revenue" fill="#c026d3" name="Revenue" radius={[4,4,0,0]} />
              <Bar dataKey="expenses" fill="#94a3b8" name="Expenses" radius={[4,4,0,0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>

        <div className="card p-5">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-semibold">Upcoming Events</h3>
            <Link to="/events" className="text-sm text-brand-600 hover:text-brand-700 flex items-center gap-1">
              View all <ArrowRight className="w-4 h-4" />
            </Link>
          </div>
          {stats.recent_events?.length ? (
            <div className="space-y-3">
              {stats.recent_events.map((event) => (
                <Link key={event.id} to={`/events/${event.id}`} className="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition-colors">
                  <div>
                    <p className="font-medium text-sm">{event.title}</p>
                    <p className="text-xs text-gray-500">{event.customer_name} · {formatDate(event.event_date)}</p>
                  </div>
                  <StatusBadge status={event.status} list={EVENT_STATUSES} />
                </Link>
              ))}
            </div>
          ) : (
            <p className="text-gray-400 text-sm text-center py-8">No upcoming events</p>
          )}
        </div>
      </div>
    </div>
  );
}
