import { useEffect, useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import toast from 'react-hot-toast';
import api from '@/lib/api';
import PageHeader, { EmptyState } from '@/components/ui/PageHeader';
import Modal from '@/components/ui/Modal';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import { formatCurrency, formatDate } from '@/lib/utils';

export default function PartnersIndex() {
  const [partners, setPartners] = useState([]);
  const [expenses, setExpenses] = useState([]);
  const [open, setOpen] = useState(false);
  const [expenseOpen, setExpenseOpen] = useState(false);
  const [deleteId, setDeleteId] = useState(null);
  const [form, setForm] = useState({ name: '', phone: '', email: '', default_split_percent: 0, notes: '' });
  const [bulkExpenses, setBulkExpenses] = useState([{ partner_id: '', category: '', description: '', amount: '', expense_date: new Date().toISOString().split('T')[0] }]);

  const load = () => {
    api.get('/partners').then(({ data }) => setPartners(data));
    api.get('/partners/expenses').then(({ data }) => setExpenses(data));
  };
  useEffect(() => { load(); }, []);

  const savePartner = async () => {
    await api.post('/partners', form);
    toast.success('Partner added');
    setOpen(false);
    load();
  };

  const saveExpenses = async () => {
    const valid = bulkExpenses.filter((e) => e.partner_id && e.amount);
    await api.post('/partners/expenses/bulk', { expenses: valid.map((e) => ({ ...e, amount: parseFloat(e.amount) })) });
    toast.success(`${valid.length} expenses saved`);
    setExpenseOpen(false);
    load();
  };

  return (
    <div>
      <PageHeader
        title="Partners"
        subtitle="Manage partners and their expenses"
        action={
          <div className="flex gap-2">
            <button className="btn-secondary" onClick={() => setExpenseOpen(true)}><Plus className="w-4 h-4" /> Multi-Expense</button>
            <button className="btn-primary" onClick={() => setOpen(true)}><Plus className="w-4 h-4" /> Add Partner</button>
          </div>
        }
      />

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        {partners.map((p) => (
          <div key={p.id} className="card p-4">
            <h3 className="font-semibold">{p.name}</h3>
            <p className="text-sm text-gray-500 mt-1">{p.phone || p.email || '—'}</p>
            <p className="text-xs text-gray-400 mt-2">Split: {p.default_split_percent}%</p>
          </div>
        ))}
      </div>

      <h3 className="font-semibold mb-3">Recent Expenses</h3>
      {expenses.length === 0 ? (
        <EmptyState title="No expenses" description="Record partner expenses for events." />
      ) : (
        <div className="card overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50"><tr>
              <th className="text-left p-3">Date</th><th className="text-left p-3">Partner</th>
              <th className="text-left p-3">Event</th><th className="text-left p-3">Category</th>
              <th className="text-right p-3">Amount</th><th className="p-3" />
            </tr></thead>
            <tbody>
              {expenses.map((exp) => (
                <tr key={exp.id} className="border-t">
                  <td className="p-3">{formatDate(exp.expense_date)}</td>
                  <td className="p-3">{exp.partner_name}</td>
                  <td className="p-3">{exp.event_title || '—'}</td>
                  <td className="p-3">{exp.category || '—'}</td>
                  <td className="p-3 text-right font-medium">{formatCurrency(exp.amount)}</td>
                  <td className="p-3"><button className="text-red-400 hover:text-red-600" onClick={() => setDeleteId(exp.id)}><Trash2 className="w-4 h-4" /></button></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Modal open={open} onClose={() => setOpen(false)} title="Add Partner" size="sm">
        <div className="space-y-3">
          <div><label className="label">Name *</label><input className="input" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></div>
          <div><label className="label">Phone</label><input className="input" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} /></div>
          <div><label className="label">Email</label><input className="input" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></div>
          <div><label className="label">Default Split %</label><input type="number" className="input" value={form.default_split_percent} onChange={(e) => setForm({ ...form, default_split_percent: e.target.value })} /></div>
          <button className="btn-primary w-full" onClick={savePartner} disabled={!form.name}>Save Partner</button>
        </div>
      </Modal>

      <Modal open={expenseOpen} onClose={() => setExpenseOpen(false)} title="Multi-Expense Mode" size="lg">
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
          <button className="btn-primary w-full" onClick={saveExpenses}>Save All</button>
        </div>
      </Modal>

      <ConfirmDialog open={!!deleteId} onClose={() => setDeleteId(null)} onConfirm={async () => { await api.delete(`/partners/expenses/${deleteId}`); load(); }} title="Delete Expense" message="Remove this expense record?" />
    </div>
  );
}
