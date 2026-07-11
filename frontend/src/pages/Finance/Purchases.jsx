import { useEffect, useState } from 'react';
import { Plus } from 'lucide-react';
import toast from 'react-hot-toast';
import api from '@/lib/api';
import PageHeader, { EmptyState } from '@/components/ui/PageHeader';
import Modal from '@/components/ui/Modal';
import { formatCurrency, formatDate } from '@/lib/utils';

export default function PurchasesIndex() {
  const [purchases, setPurchases] = useState([]);
  const [inventory, setInventory] = useState([]);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ supplier: '', purchase_date: new Date().toISOString().split('T')[0], notes: '' });
  const [lines, setLines] = useState([{ inventory_item_id: '', label: '', quantity: 1, unit_cost: 0 }]);

  const load = () => api.get('/purchases').then(({ data }) => setPurchases(data));
  useEffect(() => { load(); api.get('/inventory', { params: { per_page: 100 } }).then(({ data }) => setInventory(data.data)); }, []);

  const save = async () => {
    await api.post('/purchases', { purchase: form, line_items: lines.map((l) => ({ ...l, quantity: parseInt(l.quantity), unit_cost: parseFloat(l.unit_cost) })) });
    toast.success('Purchase recorded');
    setOpen(false);
    load();
  };

  return (
    <div>
      <PageHeader title="Purchases" subtitle="Record inventory purchases" action={<button className="btn-primary" onClick={() => setOpen(true)}><Plus className="w-4 h-4" /> New Purchase</button>} />
      {purchases.length === 0 ? (
        <EmptyState title="No purchases" description="Record purchases to track inventory costs." action={<button className="btn-primary" onClick={() => setOpen(true)}>Record Purchase</button>} />
      ) : (
        <div className="card overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50"><tr>
              <th className="text-left p-3">Date</th><th className="text-left p-3">Supplier</th>
              <th className="text-right p-3">Items</th><th className="text-right p-3">Total</th>
            </tr></thead>
            <tbody>
              {purchases.map((p) => (
                <tr key={p.id} className="border-t hover:bg-gray-50">
                  <td className="p-3">{formatDate(p.purchase_date)}</td>
                  <td className="p-3">{p.supplier || '—'}</td>
                  <td className="p-3 text-right">{p.item_count}</td>
                  <td className="p-3 text-right font-medium">{formatCurrency(p.total)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Modal open={open} onClose={() => setOpen(false)} title="New Purchase" size="lg">
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <div><label className="label">Supplier</label><input className="input" value={form.supplier} onChange={(e) => setForm({ ...form, supplier: e.target.value })} /></div>
            <div><label className="label">Date</label><input type="date" className="input" value={form.purchase_date} onChange={(e) => setForm({ ...form, purchase_date: e.target.value })} /></div>
          </div>
          {lines.map((line, i) => (
            <div key={i} className="grid grid-cols-4 gap-2 p-3 bg-gray-50 rounded-lg">
              <select className="input" value={line.inventory_item_id} onChange={(e) => {
                const u = [...lines]; const item = inventory.find((inv) => inv.id == e.target.value);
                u[i] = { ...u[i], inventory_item_id: e.target.value, label: item?.name || '', unit_cost: item?.unit_cost || 0 };
                setLines(u);
              }}>
                <option value="">Select item</option>
                {inventory.map((inv) => <option key={inv.id} value={inv.id}>{inv.name}</option>)}
              </select>
              <input className="input" placeholder="Label" value={line.label} onChange={(e) => { const u = [...lines]; u[i].label = e.target.value; setLines(u); }} />
              <input type="number" className="input" placeholder="Qty" value={line.quantity} onChange={(e) => { const u = [...lines]; u[i].quantity = e.target.value; setLines(u); }} />
              <input type="number" className="input" placeholder="Unit cost" value={line.unit_cost} onChange={(e) => { const u = [...lines]; u[i].unit_cost = e.target.value; setLines(u); }} />
            </div>
          ))}
          <button className="btn-secondary w-full" onClick={() => setLines([...lines, { inventory_item_id: '', label: '', quantity: 1, unit_cost: 0 }])}>+ Add Line</button>
          <button className="btn-primary w-full" onClick={save}>Save Purchase</button>
        </div>
      </Modal>
    </div>
  );
}
