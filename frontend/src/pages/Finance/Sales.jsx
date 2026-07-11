import { useEffect, useState } from 'react';
import { Plus } from 'lucide-react';
import toast from 'react-hot-toast';
import api from '@/lib/api';
import PageHeader, { EmptyState } from '@/components/ui/PageHeader';
import Modal from '@/components/ui/Modal';
import { formatCurrency, formatDate } from '@/lib/utils';

export default function SalesIndex() {
  const [sales, setSales] = useState([]);
  const [customers, setCustomers] = useState([]);
  const [events, setEvents] = useState([]);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ customer_id: '', event_id: '', sale_date: new Date().toISOString().split('T')[0], notes: '' });
  const [lines, setLines] = useState([{ label: '', quantity: 1, unit_price: 0 }]);

  const load = () => api.get('/sales').then(({ data }) => setSales(data));
  useEffect(() => {
    load();
    api.get('/customers').then(({ data }) => setCustomers(data));
    api.get('/events').then(({ data }) => setEvents(data.data || data));
  }, []);

  const save = async () => {
    await api.post('/sales', { sale: form, line_items: lines.map((l) => ({ ...l, quantity: parseInt(l.quantity), unit_price: parseFloat(l.unit_price) })) });
    toast.success('Sale recorded');
    setOpen(false);
    load();
  };

  return (
    <div>
      <PageHeader title="Sales" subtitle="Track revenue from events" action={<button className="btn-primary" onClick={() => setOpen(true)}><Plus className="w-4 h-4" /> New Sale</button>} />
      {sales.length === 0 ? (
        <EmptyState title="No sales recorded" description="Record sales to track your revenue." action={<button className="btn-primary" onClick={() => setOpen(true)}>Record Sale</button>} />
      ) : (
        <div className="card overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50"><tr>
              <th className="text-left p-3">Date</th><th className="text-left p-3">Customer</th>
              <th className="text-left p-3">Event</th><th className="text-right p-3">Total</th>
            </tr></thead>
            <tbody>
              {sales.map((s) => (
                <tr key={s.id} className="border-t hover:bg-gray-50">
                  <td className="p-3">{formatDate(s.sale_date)}</td>
                  <td className="p-3">{s.customer_name || '—'}</td>
                  <td className="p-3">{s.event_title || '—'}</td>
                  <td className="p-3 text-right font-medium text-green-600">{formatCurrency(s.total)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Modal open={open} onClose={() => setOpen(false)} title="New Sale" size="lg">
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <select className="input" value={form.customer_id} onChange={(e) => setForm({ ...form, customer_id: e.target.value })}>
              <option value="">Customer</option>
              {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
            <select className="input" value={form.event_id} onChange={(e) => setForm({ ...form, event_id: e.target.value })}>
              <option value="">Event</option>
              {events.map((ev) => <option key={ev.id} value={ev.id}>{ev.title}</option>)}
            </select>
          </div>
          {lines.map((line, i) => (
            <div key={i} className="grid grid-cols-3 gap-2 p-3 bg-gray-50 rounded-lg">
              <input className="input" placeholder="Description" value={line.label} onChange={(e) => { const u = [...lines]; u[i].label = e.target.value; setLines(u); }} />
              <input type="number" className="input" placeholder="Qty" value={line.quantity} onChange={(e) => { const u = [...lines]; u[i].quantity = e.target.value; setLines(u); }} />
              <input type="number" className="input" placeholder="Price" value={line.unit_price} onChange={(e) => { const u = [...lines]; u[i].unit_price = e.target.value; setLines(u); }} />
            </div>
          ))}
          <button className="btn-secondary w-full" onClick={() => setLines([...lines, { label: '', quantity: 1, unit_price: 0 }])}>+ Add Line</button>
          <button className="btn-primary w-full" onClick={save}>Save Sale</button>
        </div>
      </Modal>
    </div>
  );
}
