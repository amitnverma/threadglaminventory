import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import toast from 'react-hot-toast';
import api from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';

export default function InventoryForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [categories, setCategories] = useState([]);
  const [form, setForm] = useState({
    name: '', sku: '', category_id: '', description: '', quantity_on_hand: 0,
    unit_cost: 0, rental_price: 0, sale_price: 0, condition_status: 'good', reorder_level: 0,
  });
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    api.get('/inventory/categories').then(({ data }) => setCategories(data));
    if (id) {
      api.get(`/inventory/${id}`).then(({ data }) => setForm(data));
    }
  }, [id]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      if (id) {
        await api.put(`/inventory/${id}`, form);
        toast.success('Item updated');
        navigate(`/inventory/${id}`);
      } else {
        const { data } = await api.post('/inventory', form);
        toast.success('Item created');
        navigate(`/inventory/${data.id}`);
      }
    } catch {
      toast.error('Save failed');
    } finally {
      setSaving(false);
    }
  };

  const set = (field) => (e) => setForm({ ...form, [field]: e.target.value });

  return (
    <div>
      <PageHeader
        title={id ? 'Edit Item' : 'New Inventory Item'}
        breadcrumbs={[{ to: '/inventory', label: 'Inventory' }, { label: id ? 'Edit' : 'New' }]}
      />
      <form onSubmit={handleSubmit} className="card p-6 max-w-2xl">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="md:col-span-2">
            <label className="label">Name *</label>
            <input className="input" value={form.name} onChange={set('name')} required />
          </div>
          <div>
            <label className="label">SKU</label>
            <input className="input" value={form.sku} onChange={set('sku')} />
          </div>
          <div>
            <label className="label">Category</label>
            <select className="input" value={form.category_id} onChange={set('category_id')}>
              <option value="">Select category</option>
              {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
          </div>
          <div className="md:col-span-2">
            <label className="label">Description</label>
            <textarea className="input" rows={3} value={form.description} onChange={set('description')} />
          </div>
          {!id && (
            <div>
              <label className="label">Initial Quantity</label>
              <input type="number" className="input" value={form.quantity_on_hand} onChange={set('quantity_on_hand')} />
            </div>
          )}
          <div>
            <label className="label">Unit Cost</label>
            <input type="number" step="0.01" className="input" value={form.unit_cost} onChange={set('unit_cost')} />
          </div>
          <div>
            <label className="label">Rental Price</label>
            <input type="number" step="0.01" className="input" value={form.rental_price} onChange={set('rental_price')} />
          </div>
          <div>
            <label className="label">Sale Price</label>
            <input type="number" step="0.01" className="input" value={form.sale_price} onChange={set('sale_price')} />
          </div>
          <div>
            <label className="label">Reorder Level</label>
            <input type="number" className="input" value={form.reorder_level} onChange={set('reorder_level')} />
          </div>
          <div>
            <label className="label">Condition</label>
            <select className="input" value={form.condition_status} onChange={set('condition_status')}>
              <option value="excellent">Excellent</option>
              <option value="good">Good</option>
              <option value="fair">Fair</option>
              <option value="poor">Poor</option>
            </select>
          </div>
        </div>
        <div className="flex gap-3 mt-6">
          <button type="submit" className="btn-primary" disabled={saving}>{saving ? 'Saving...' : 'Save Item'}</button>
          <button type="button" className="btn-secondary" onClick={() => navigate(-1)}>Cancel</button>
        </div>
      </form>
    </div>
  );
}
