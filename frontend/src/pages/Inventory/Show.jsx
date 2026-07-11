import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Edit, Trash2, Copy, Plus, Minus } from 'lucide-react';
import toast from 'react-hot-toast';
import api from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import ImageUploader, { ImageGallery } from '@/components/ImageUploader';
import Modal from '@/components/ui/Modal';
import { formatCurrency } from '@/lib/utils';

export default function InventoryShow() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [item, setItem] = useState(null);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [adjustOpen, setAdjustOpen] = useState(false);
  const [adjust, setAdjust] = useState({ adjustment_type: 'add', quantity: 1, reason: '' });

  const load = () => api.get(`/inventory/${id}`).then(({ data }) => setItem(data));

  useEffect(() => { load(); }, [id]);

  const handleDelete = async () => {
    await api.delete(`/inventory/${id}`);
    toast.success('Item deleted');
    navigate('/inventory');
  };

  const handleDuplicate = async () => {
    const { data } = await api.post(`/inventory/${id}/duplicate`);
    toast.success('Item duplicated');
    navigate(`/inventory/${data.id}/edit`);
  };

  const handleAdjust = async () => {
    await api.post(`/inventory/${id}/adjust`, adjust);
    toast.success('Stock adjusted');
    setAdjustOpen(false);
    load();
  };

  const handleImageDelete = async (attachmentId) => {
    await api.delete(`/attachments/${attachmentId}`);
    load();
  };

  if (!item) return <div className="animate-pulse h-64 bg-gray-200 rounded-xl" />;

  return (
    <div>
      <PageHeader
        title={item.name}
        breadcrumbs={[{ to: '/inventory', label: 'Inventory' }, { label: item.name }]}
        action={
          <div className="flex gap-2">
            <button className="btn-secondary" onClick={() => setAdjustOpen(true)}><Plus className="w-4 h-4" /> Adjust Stock</button>
            <button className="btn-secondary" onClick={handleDuplicate}><Copy className="w-4 h-4" /> Duplicate</button>
            <Link to={`/inventory/${id}/edit`} className="btn-secondary"><Edit className="w-4 h-4" /> Edit</Link>
            <button className="btn-danger" onClick={() => setDeleteOpen(true)}><Trash2 className="w-4 h-4" /></button>
          </div>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2 space-y-6">
          <div className="card p-5">
            <h3 className="font-semibold mb-4">Images</h3>
            <ImageUploader type="inventory" id={id} onUploaded={load} />
            <div className="mt-4">
              <ImageGallery images={item.images} onDelete={handleImageDelete} />
            </div>
          </div>
          {item.description && (
            <div className="card p-5">
              <h3 className="font-semibold mb-2">Description</h3>
              <p className="text-gray-600 text-sm">{item.description}</p>
            </div>
          )}
        </div>

        <div className="space-y-4">
          <div className="card p-5 space-y-3">
            <div className="flex justify-between"><span className="text-gray-500 text-sm">SKU</span><span className="font-medium">{item.sku || '—'}</span></div>
            <div className="flex justify-between"><span className="text-gray-500 text-sm">Category</span><span>{item.category_name || '—'}</span></div>
            <div className="flex justify-between"><span className="text-gray-500 text-sm">Quantity</span><span className="font-bold text-lg">{item.quantity_on_hand}</span></div>
            <div className="flex justify-between"><span className="text-gray-500 text-sm">Unit Cost</span><span>{formatCurrency(item.unit_cost)}</span></div>
            <div className="flex justify-between"><span className="text-gray-500 text-sm">Rental Price</span><span className="text-brand-600 font-semibold">{formatCurrency(item.rental_price)}</span></div>
            <div className="flex justify-between"><span className="text-gray-500 text-sm">Sale Price</span><span>{formatCurrency(item.sale_price)}</span></div>
            <div className="flex justify-between"><span className="text-gray-500 text-sm">Condition</span><span className="capitalize">{item.condition_status}</span></div>
          </div>

          {item.adjustments?.length > 0 && (
            <div className="card p-5">
              <h3 className="font-semibold mb-3 text-sm">Recent Adjustments</h3>
              <div className="space-y-2">
                {item.adjustments.slice(0, 5).map((a) => (
                  <div key={a.id} className="text-xs text-gray-500 flex justify-between">
                    <span className="capitalize">{a.adjustment_type} {a.quantity}</span>
                    <span>{new Date(a.created_at).toLocaleDateString()}</span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>

      <ConfirmDialog open={deleteOpen} onClose={() => setDeleteOpen(false)} onConfirm={handleDelete} title="Delete Item" message="This will permanently remove this inventory item." />
      <Modal open={adjustOpen} onClose={() => setAdjustOpen(false)} title="Adjust Stock" size="sm">
        <div className="space-y-4">
          <div>
            <label className="label">Type</label>
            <select className="input" value={adjust.adjustment_type} onChange={(e) => setAdjust({ ...adjust, adjustment_type: e.target.value })}>
              <option value="add">Add Stock</option>
              <option value="remove">Remove Stock</option>
              <option value="set">Set Quantity</option>
            </select>
          </div>
          <div>
            <label className="label">Quantity</label>
            <input type="number" className="input" value={adjust.quantity} onChange={(e) => setAdjust({ ...adjust, quantity: parseInt(e.target.value) })} />
          </div>
          <div>
            <label className="label">Reason</label>
            <input className="input" value={adjust.reason} onChange={(e) => setAdjust({ ...adjust, reason: e.target.value })} />
          </div>
          <button className="btn-primary w-full" onClick={handleAdjust}>Apply Adjustment</button>
        </div>
      </Modal>
    </div>
  );
}
