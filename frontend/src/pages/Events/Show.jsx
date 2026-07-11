import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { FileText, DollarSign, Users, Image, BarChart3, Plus } from 'lucide-react';
import api from '@/lib/api';
import PageHeader, { StatusBadge } from '@/components/ui/PageHeader';
import ImageUploader, { ImageGallery } from '@/components/ImageUploader';
import Modal from '@/components/ui/Modal';
import { EVENT_STATUSES, formatCurrency, formatDate } from '@/lib/utils';
import toast from 'react-hot-toast';

const TABS = [
  { id: 'overview', label: 'Overview', icon: FileText },
  { id: 'estimates', label: 'Estimates', icon: FileText },
  { id: 'expenses', label: 'Expenses', icon: DollarSign },
  { id: 'images', label: 'Images', icon: Image },
  { id: 'pnl', label: 'P&L', icon: BarChart3 },
];

export default function EventShow() {
  const { id } = useParams();
  const [event, setEvent] = useState(null);
  const [tab, setTab] = useState('overview');
  const [expenseOpen, setExpenseOpen] = useState(false);
  const [partners, setPartners] = useState([]);
  const [bulkExpenses, setBulkExpenses] = useState([{ partner_id: '', category: '', description: '', amount: '', expense_date: new Date().toISOString().split('T')[0] }]);

  const load = () => api.get(`/events/${id}`).then(({ data }) => setEvent(data));

  useEffect(() => { load(); api.get('/partners').then(({ data }) => setPartners(data)); }, [id]);

  const handleImageDelete = async (attachmentId) => {
    await api.delete(`/attachments/${attachmentId}`);
    load();
  };

  const saveBulkExpenses = async () => {
    const valid = bulkExpenses.filter((e) => e.partner_id && e.amount);
    await api.post('/partners/expenses/bulk', { expenses: valid.map((e) => ({ ...e, event_id: parseInt(id), amount: parseFloat(e.amount) })) });
    toast.success(`${valid.length} expenses recorded`);
    setExpenseOpen(false);
    load();
  };

  if (!event) return <div className="animate-pulse h-64 bg-gray-200 rounded-xl" />;

  return (
    <div>
      <PageHeader
        title={event.title}
        subtitle={`${event.customer_name} · ${formatDate(event.event_date)}`}
        breadcrumbs={[{ to: '/events', label: 'Events' }, { label: event.title }]}
        action={
          <div className="flex gap-2">
            <StatusBadge status={event.status} list={EVENT_STATUSES} />
            <Link to={`/events/${id}/edit`} className="btn-secondary">Edit</Link>
            <Link to={`/estimates/new?event_id=${id}&customer_id=${event.customer_id}`} className="btn-primary">New Estimate</Link>
          </div>
        }
      />

      <div className="flex gap-1 border-b border-gray-200 mb-6 overflow-x-auto">
        {TABS.map((t) => (
          <button
            key={t.id}
            onClick={() => setTab(t.id)}
            className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
              tab === t.id ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            <t.icon className="w-4 h-4" />{t.label}
          </button>
        ))}
      </div>

      {tab === 'overview' && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="card p-5 space-y-3">
            <h3 className="font-semibold">Event Details</h3>
            <div className="text-sm space-y-2">
              <div className="flex justify-between"><span className="text-gray-500">Type</span><span>{event.ceremony_type || '—'}</span></div>
              <div className="flex justify-between"><span className="text-gray-500">Venue</span><span>{event.venue || '—'}</span></div>
              <div className="flex justify-between"><span className="text-gray-500">Customer</span><span>{event.customer_name}</span></div>
              <div className="flex justify-between"><span className="text-gray-500">Phone</span><span>{event.customer_phone || '—'}</span></div>
            </div>
          </div>
          {event.internal_notes && (
            <div className="card p-5"><h3 className="font-semibold mb-2">Internal Notes</h3><p className="text-sm text-gray-600">{event.internal_notes}</p></div>
          )}
        </div>
      )}

      {tab === 'estimates' && (
        <div className="space-y-3">
          {event.estimates?.length ? event.estimates.map((est) => (
            <Link key={est.id} to={`/estimates/${est.id}`} className="card p-4 flex justify-between hover:shadow-md transition-shadow">
              <div><p className="font-medium">{est.title}</p><p className="text-sm text-gray-500">v{est.version}</p></div>
              <div className="text-right"><p className="font-semibold">{formatCurrency(est.total)}</p><StatusBadge status={est.status} list={[{ value: 'draft', label: 'Draft', color: 'bg-gray-100 text-gray-800' }, { value: 'sent', label: 'Sent', color: 'bg-blue-100 text-blue-800' }, { value: 'approved', label: 'Approved', color: 'bg-green-100 text-green-800' }]} /></div>
            </Link>
          )) : <p className="text-gray-400 text-center py-8">No estimates yet</p>}
        </div>
      )}

      {tab === 'expenses' && (
        <div>
          <div className="flex justify-end mb-4">
            <button className="btn-primary" onClick={() => setExpenseOpen(true)}><Plus className="w-4 h-4" /> Add Expenses</button>
          </div>
          {event.partner_expenses?.length ? (
            <div className="card overflow-hidden">
              <table className="w-full text-sm">
                <thead className="bg-gray-50"><tr>
                  <th className="text-left p-3">Partner</th><th className="text-left p-3">Category</th>
                  <th className="text-left p-3">Description</th><th className="text-right p-3">Amount</th><th className="text-left p-3">Date</th>
                </tr></thead>
                <tbody>
                  {event.partner_expenses.map((exp) => (
                    <tr key={exp.id} className="border-t">
                      <td className="p-3">{exp.partner_name}</td>
                      <td className="p-3">{exp.category || '—'}</td>
                      <td className="p-3">{exp.description || '—'}</td>
                      <td className="p-3 text-right font-medium">{formatCurrency(exp.amount)}</td>
                      <td className="p-3">{formatDate(exp.expense_date)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : <p className="text-gray-400 text-center py-8">No partner expenses recorded</p>}
        </div>
      )}

      {tab === 'images' && (
        <div className="card p-5">
          <ImageUploader type="event" id={id} onUploaded={load} />
          <div className="mt-4"><ImageGallery images={event.images} onDelete={handleImageDelete} /></div>
        </div>
      )}

      {tab === 'pnl' && event.profit_loss && (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="card p-5 text-center"><p className="text-sm text-gray-500">Revenue</p><p className="text-2xl font-bold text-green-600">{formatCurrency(event.profit_loss.revenue)}</p></div>
          <div className="card p-5 text-center"><p className="text-sm text-gray-500">Expenses</p><p className="text-2xl font-bold text-red-600">{formatCurrency(event.profit_loss.expenses)}</p></div>
          <div className="card p-5 text-center"><p className="text-sm text-gray-500">Profit</p><p className={`text-2xl font-bold ${event.profit_loss.profit >= 0 ? 'text-brand-600' : 'text-red-600'}`}>{formatCurrency(event.profit_loss.profit)}</p></div>
        </div>
      )}

      <Modal open={expenseOpen} onClose={() => setExpenseOpen(false)} title="Record Partner Expenses" size="lg">
        <div className="space-y-3">
          {bulkExpenses.map((exp, i) => (
            <div key={i} className="grid grid-cols-2 md:grid-cols-5 gap-2 p-3 bg-gray-50 rounded-lg">
              <select className="input" value={exp.partner_id} onChange={(e) => { const u = [...bulkExpenses]; u[i].partner_id = e.target.value; setBulkExpenses(u); }}>
                <option value="">Partner</option>
                {partners.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
              <input className="input" placeholder="Category" value={exp.category} onChange={(e) => { const u = [...bulkExpenses]; u[i].category = e.target.value; setBulkExpenses(u); }} />
              <input className="input" placeholder="Description" value={exp.description} onChange={(e) => { const u = [...bulkExpenses]; u[i].description = e.target.value; setBulkExpenses(u); }} />
              <input type="number" className="input" placeholder="Amount" value={exp.amount} onChange={(e) => { const u = [...bulkExpenses]; u[i].amount = e.target.value; setBulkExpenses(u); }} />
              <input type="date" className="input" value={exp.expense_date} onChange={(e) => { const u = [...bulkExpenses]; u[i].expense_date = e.target.value; setBulkExpenses(u); }} />
            </div>
          ))}
          <button className="btn-secondary w-full" onClick={() => setBulkExpenses([...bulkExpenses, { partner_id: '', category: '', description: '', amount: '', expense_date: new Date().toISOString().split('T')[0] }])}>+ Add Row</button>
          <button className="btn-primary w-full" onClick={saveBulkExpenses}>Save All Expenses</button>
        </div>
      </Modal>
    </div>
  );
}
