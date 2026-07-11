import { useEffect, useState, useCallback } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import {
  DndContext, DragOverlay, closestCenter, PointerSensor, useSensor, useSensors,
} from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy, useSortable, arrayMove } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical, Plus, Trash2, Save, Search, AlertTriangle, Copy, FileSignature } from 'lucide-react';
import toast from 'react-hot-toast';
import api, { getImageUrl } from '@/lib/api';
import PageHeader from '@/components/ui/PageHeader';
import { formatCurrency } from '@/lib/utils';

function DraggableCatalogItem({ item }) {
  return (
    <div
      draggable
      onDragStart={(e) => {
        e.dataTransfer.setData('application/json', JSON.stringify({
          type: 'inventory', inventory_item_id: item.id, label: item.name,
          unit_price: item.rental_price, unit_cost: item.unit_cost, quantity: 1, line_type: 'inventory',
        }));
      }}
      className="flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg cursor-grab hover:border-brand-300 hover:shadow-sm transition-all active:cursor-grabbing"
    >
      <div className="w-10 h-10 rounded bg-gray-100 flex-shrink-0 overflow-hidden">
        {item.thumbnail ? <img src={getImageUrl(item.thumbnail)} className="w-full h-full object-cover" /> : <span className="flex items-center justify-center h-full text-lg">📦</span>}
      </div>
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium truncate">{item.name}</p>
        <p className="text-xs text-gray-500">{formatCurrency(item.rental_price)} · Qty: {item.quantity_on_hand}</p>
      </div>
    </div>
  );
}

function SortableLineItem({ item, index, onUpdate, onRemove }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: item._id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  return (
    <div ref={setNodeRef} style={style} className="flex items-center gap-2 p-3 bg-white border border-gray-200 rounded-lg group">
      <button {...attributes} {...listeners} className="text-gray-300 hover:text-gray-500 cursor-grab"><GripVertical className="w-4 h-4" /></button>
      <div className="flex-1 grid grid-cols-12 gap-2 items-center">
        <div className="col-span-5">
          <input className="input text-sm" value={item.label} onChange={(e) => onUpdate(index, 'label', e.target.value)} />
        </div>
        <div className="col-span-2">
          <input type="number" className="input text-sm" value={item.quantity} min="0" step="0.5" onChange={(e) => onUpdate(index, 'quantity', parseFloat(e.target.value) || 0)} />
        </div>
        <div className="col-span-2">
          <input type="number" className="input text-sm" value={item.unit_price} min="0" step="0.01" onChange={(e) => onUpdate(index, 'unit_price', parseFloat(e.target.value) || 0)} />
        </div>
        <div className="col-span-2 text-right text-sm font-medium">
          {formatCurrency(item.quantity * item.unit_price)}
        </div>
        <div className="col-span-1">
          <button className="text-gray-300 hover:text-red-500 opacity-0 group-hover:opacity-100" onClick={() => onRemove(index)}><Trash2 className="w-4 h-4" /></button>
        </div>
      </div>
    </div>
  );
}

let lineIdCounter = 0;
const newLineId = () => `line-${++lineIdCounter}`;

export default function EstimateBuilder() {
  const { id } = useParams();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();

  const [catalog, setCatalog] = useState([]);
  const [categories, setCategories] = useState([]);
  const [customers, setCustomers] = useState([]);
  const [events, setEvents] = useState([]);
  const [catalogSearch, setCatalogSearch] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [saving, setSaving] = useState(false);
  const [activeId, setActiveId] = useState(null);

  const [estimate, setEstimate] = useState({
    customer_id: searchParams.get('customer_id') || '',
    event_id: searchParams.get('event_id') || '',
    title: 'New Estimate',
    status: 'draft',
    tax_percent: 18,
    discount_type: 'percent',
    discount_value: 0,
    valid_until: '',
    notes: '',
  });
  const [lineItems, setLineItems] = useState([]);

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 5 } }));

  useEffect(() => {
    api.get('/estimates/catalog', { params: { search: catalogSearch, category_id: categoryFilter || undefined } }).then(({ data }) => setCatalog(data));
  }, [catalogSearch, categoryFilter]);

  useEffect(() => {
    api.get('/inventory/categories').then(({ data }) => setCategories(data));
    api.get('/customers').then(({ data }) => setCustomers(data));
    api.get('/events').then(({ data }) => setEvents(data.data || data));
  }, []);

  useEffect(() => {
    if (id) {
      api.get(`/estimates/${id}`).then(({ data }) => {
        setEstimate(data);
        setLineItems((data.line_items || []).map((item) => ({ ...item, _id: newLineId() })));
      });
    }
  }, [id]);

  const totals = useCallback(() => {
    const subtotal = lineItems.filter((i) => i.line_type !== 'discount').reduce((s, i) => s + i.quantity * i.unit_price, 0);
    const discountAmt = estimate.discount_type === 'percent' ? (subtotal * estimate.discount_value) / 100 : estimate.discount_value;
    const taxable = Math.max(0, subtotal - discountAmt);
    const taxAmt = (taxable * estimate.tax_percent) / 100;
    const total = taxable + taxAmt;
    const totalCost = lineItems.reduce((s, i) => s + (i.quantity * (i.unit_cost || 0)), 0);
    return { subtotal, discountAmt, taxAmt, total, totalCost, profit: total - totalCost, margin: total > 0 ? ((total - totalCost) / total) * 100 : 0 };
  }, [lineItems, estimate]);

  const t = totals();

  const handleDrop = (e) => {
    e.preventDefault();
    try {
      const data = JSON.parse(e.dataTransfer.getData('application/json'));
      setLineItems([...lineItems, { ...data, _id: newLineId(), sort_order: lineItems.length }]);
    } catch {}
  };

  const updateLine = (index, field, value) => {
    const updated = [...lineItems];
    updated[index] = { ...updated[index], [field]: value };
    setLineItems(updated);
  };

  const removeLine = (index) => setLineItems(lineItems.filter((_, i) => i !== index));

  const addCustomLine = (type = 'custom') => {
    setLineItems([...lineItems, { _id: newLineId(), line_type: type, label: type === 'labor' ? 'Labor / Service Fee' : 'Custom Item', quantity: 1, unit_price: 0, unit_cost: 0, sort_order: lineItems.length }]);
  };

  const handleSortEnd = (event) => {
    const { active, over } = event;
    if (active.id !== over?.id) {
      const oldIndex = lineItems.findIndex((i) => i._id === active.id);
      const newIndex = lineItems.findIndex((i) => i._id === over.id);
      setLineItems(arrayMove(lineItems, oldIndex, newIndex));
    }
    setActiveId(null);
  };

  const handleSave = async () => {
    if (!estimate.customer_id) { toast.error('Select a customer'); return; }
    setSaving(true);
    try {
      const payload = {
        estimate,
        line_items: lineItems.map(({ _id, ...item }, i) => ({ ...item, sort_order: i })),
      };
      if (id) {
        await api.put(`/estimates/${id}`, payload);
        toast.success('Estimate saved');
      } else {
        const { data } = await api.post('/estimates', payload);
        toast.success('Estimate created');
        navigate(`/estimates/${data.id}`, { replace: true });
      }
    } catch {
      toast.error('Save failed');
    } finally {
      setSaving(false);
    }
  };

  const handleClone = async () => {
    if (!id) return;
    const { data } = await api.post(`/estimates/${id}/clone`);
    toast.success('Estimate cloned');
    navigate(`/estimates/${data.id}`);
  };

  const createContract = async () => {
    if (!id) { toast.error('Save estimate first'); return; }
    const { data } = await api.post(`/contracts/from-estimate/${id}`);
    toast.success('Contract created');
    navigate(`/contracts/${data.id}`);
  };

  return (
    <div>
      <PageHeader
        title={id ? 'Edit Estimate' : 'New Estimate'}
        breadcrumbs={[{ to: '/estimates', label: 'Estimates' }, { label: id ? 'Edit' : 'Builder' }]}
        action={
          <div className="flex gap-2">
            {id && <button className="btn-secondary" onClick={handleClone}><Copy className="w-4 h-4" /> Clone</button>}
            {id && <button className="btn-secondary" onClick={createContract}><FileSignature className="w-4 h-4" /> To Contract</button>}
            <button className="btn-primary" onClick={handleSave} disabled={saving}><Save className="w-4 h-4" /> {saving ? 'Saving...' : 'Save'}</button>
          </div>
        }
      />

      <div className="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
        <input className="input" placeholder="Estimate title" value={estimate.title} onChange={(e) => setEstimate({ ...estimate, title: e.target.value })} />
        <select className="input" value={estimate.customer_id} onChange={(e) => setEstimate({ ...estimate, customer_id: e.target.value })}>
          <option value="">Select customer</option>
          {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
        <select className="input" value={estimate.event_id} onChange={(e) => setEstimate({ ...estimate, event_id: e.target.value })}>
          <option value="">Link to event (optional)</option>
          {events.map((ev) => <option key={ev.id} value={ev.id}>{ev.title}</option>)}
        </select>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-4" style={{ minHeight: '600px' }}>
        {/* Catalog Panel */}
        <div className="lg:col-span-3 card flex flex-col overflow-hidden">
          <div className="p-3 border-b border-gray-100">
            <div className="relative mb-2">
              <Search className="absolute left-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <input className="input pl-8 text-sm" placeholder="Search inventory..." value={catalogSearch} onChange={(e) => setCatalogSearch(e.target.value)} />
            </div>
            <div className="flex flex-wrap gap-1">
              <button className={`text-xs px-2 py-1 rounded-full ${!categoryFilter ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-600'}`} onClick={() => setCategoryFilter('')}>All</button>
              {categories.map((c) => (
                <button key={c.id} className={`text-xs px-2 py-1 rounded-full ${categoryFilter == c.id ? 'bg-brand-100 text-brand-700' : 'bg-gray-100 text-gray-600'}`} onClick={() => setCategoryFilter(c.id)}>{c.name}</button>
              ))}
            </div>
          </div>
          <div className="flex-1 overflow-y-auto p-3 space-y-2">
            {catalog.map((item) => <DraggableCatalogItem key={item.id} item={item} />)}
          </div>
        </div>

        {/* Canvas */}
        <div className="lg:col-span-6 card flex flex-col overflow-hidden">
          <div className="p-3 border-b border-gray-100 flex items-center justify-between">
            <h3 className="font-semibold text-sm">Estimate Items</h3>
            <div className="flex gap-2">
              <button className="btn-ghost text-xs" onClick={() => addCustomLine('custom')}><Plus className="w-3 h-3" /> Custom</button>
              <button className="btn-ghost text-xs" onClick={() => addCustomLine('labor')}><Plus className="w-3 h-3" /> Labor</button>
            </div>
          </div>
          <div
            className="flex-1 overflow-y-auto p-3"
            onDragOver={(e) => e.preventDefault()}
            onDrop={handleDrop}
          >
            {lineItems.length === 0 ? (
              <div className="h-full flex flex-col items-center justify-center text-gray-400 border-2 border-dashed border-gray-200 rounded-xl m-2">
                <p className="text-sm font-medium">Drag inventory items here</p>
                <p className="text-xs mt-1">or add custom items above</p>
              </div>
            ) : (
              <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleSortEnd}>
                <SortableContext items={lineItems.map((i) => i._id)} strategy={verticalListSortingStrategy}>
                  <div className="space-y-2">
                    <div className="grid grid-cols-12 gap-2 px-3 text-xs font-medium text-gray-400 uppercase">
                      <div className="col-span-5">Item</div><div className="col-span-2">Qty</div><div className="col-span-2">Rate</div><div className="col-span-2 text-right">Amount</div><div className="col-span-1" />
                    </div>
                    {lineItems.map((item, index) => (
                      <div key={item._id}>
                        <SortableLineItem item={item} index={index} onUpdate={updateLine} onRemove={removeLine} />
                        {item.inventory_item_id && item.quantity > (catalog.find((c) => c.id === item.inventory_item_id)?.quantity_on_hand || 999) && (
                          <p className="text-xs text-orange-600 flex items-center gap-1 mt-1 ml-8"><AlertTriangle className="w-3 h-3" /> Exceeds available stock</p>
                        )}
                      </div>
                    ))}
                  </div>
                </SortableContext>
              </DndContext>
            )}
          </div>
        </div>

        {/* Totals Sidebar */}
        <div className="lg:col-span-3 space-y-4">
          <div className="card p-4 space-y-3">
            <h3 className="font-semibold text-sm">Summary</h3>
            <div className="flex justify-between text-sm"><span className="text-gray-500">Subtotal</span><span>{formatCurrency(t.subtotal)}</span></div>
            <div className="flex items-center gap-2">
              <span className="text-sm text-gray-500 flex-1">Discount</span>
              <select className="input w-20 text-xs py-1" value={estimate.discount_type} onChange={(e) => setEstimate({ ...estimate, discount_type: e.target.value })}>
                <option value="percent">%</option><option value="flat">₹</option>
              </select>
              <input type="number" className="input w-20 text-xs py-1" value={estimate.discount_value} onChange={(e) => setEstimate({ ...estimate, discount_value: parseFloat(e.target.value) || 0 })} />
            </div>
            <div className="flex justify-between text-sm text-red-600"><span>Discount</span><span>-{formatCurrency(t.discountAmt)}</span></div>
            <div className="flex items-center gap-2">
              <span className="text-sm text-gray-500 flex-1">Tax %</span>
              <input type="number" className="input w-20 text-xs py-1" value={estimate.tax_percent} onChange={(e) => setEstimate({ ...estimate, tax_percent: parseFloat(e.target.value) || 0 })} />
            </div>
            <div className="flex justify-between text-sm"><span className="text-gray-500">Tax</span><span>{formatCurrency(t.taxAmt)}</span></div>
            <div className="border-t pt-3 flex justify-between font-bold text-lg"><span>Total</span><span className="text-brand-600">{formatCurrency(t.total)}</span></div>
          </div>

          <div className="card p-4 space-y-2">
            <h3 className="font-semibold text-sm">Profit Preview</h3>
            <div className="flex justify-between text-sm"><span className="text-gray-500">Cost</span><span>{formatCurrency(t.totalCost)}</span></div>
            <div className="flex justify-between text-sm"><span className="text-gray-500">Profit</span><span className={t.profit >= 0 ? 'text-green-600' : 'text-red-600'}>{formatCurrency(t.profit)}</span></div>
            <div className="flex justify-between text-sm"><span className="text-gray-500">Margin</span><span>{t.margin.toFixed(1)}%</span></div>
            <div className="w-full bg-gray-200 rounded-full h-2 mt-2">
              <div className="bg-brand-500 h-2 rounded-full transition-all" style={{ width: `${Math.min(100, Math.max(0, t.margin))}%` }} />
            </div>
          </div>

          {id && (
            <div className="card p-4">
              <label className="label">Status</label>
              <select className="input" value={estimate.status} onChange={async (e) => {
                setEstimate({ ...estimate, status: e.target.value });
                await api.patch(`/estimates/${id}/status`, { status: e.target.value });
                toast.success('Status updated');
              }}>
                <option value="draft">Draft</option>
                <option value="sent">Sent</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
              </select>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
