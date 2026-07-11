import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Search, Grid, List, AlertTriangle } from 'lucide-react';
import api from '@/lib/api';
import PageHeader, { EmptyState } from '@/components/ui/PageHeader';
import { formatCurrency } from '@/lib/utils';
import { getImageUrl } from '@/lib/api';

export default function InventoryIndex() {
  const [items, setItems] = useState([]);
  const [categories, setCategories] = useState([]);
  const [search, setSearch] = useState('');
  const [categoryId, setCategoryId] = useState('');
  const [view, setView] = useState('grid');
  const [loading, setLoading] = useState(true);

  const load = () => {
    setLoading(true);
    api.get('/inventory', { params: { search, category_id: categoryId || undefined } })
      .then(({ data }) => setItems(data.data))
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, [search, categoryId]);
  useEffect(() => { api.get('/inventory/categories').then(({ data }) => setCategories(data)); }, []);

  return (
    <div>
      <PageHeader
        title="Inventory"
        subtitle="Manage your event inventory items"
        action={<Link to="/inventory/new" className="btn-primary"><Plus className="w-4 h-4" /> Add Item</Link>}
      />

      <div className="card p-4 mb-4 flex flex-wrap gap-3 items-center">
        <div className="relative flex-1 min-w-[200px]">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input className="input pl-9" placeholder="Search items..." value={search} onChange={(e) => setSearch(e.target.value)} />
        </div>
        <select className="input w-auto" value={categoryId} onChange={(e) => setCategoryId(e.target.value)}>
          <option value="">All Categories</option>
          {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
        <div className="flex border border-gray-200 rounded-lg overflow-hidden">
          <button className={`p-2 ${view === 'grid' ? 'bg-brand-50 text-brand-600' : 'text-gray-400'}`} onClick={() => setView('grid')}><Grid className="w-4 h-4" /></button>
          <button className={`p-2 ${view === 'table' ? 'bg-brand-50 text-brand-600' : 'text-gray-400'}`} onClick={() => setView('table')}><List className="w-4 h-4" /></button>
        </div>
      </div>

      {loading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {[1,2,3,4].map(i => <div key={i} className="h-48 bg-gray-200 rounded-xl animate-pulse" />)}
        </div>
      ) : items.length === 0 ? (
        <EmptyState title="No inventory items" description="Start building your inventory catalog for events." action={<Link to="/inventory/new" className="btn-primary">Add First Item</Link>} />
      ) : view === 'grid' ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {items.map((item) => (
            <Link key={item.id} to={`/inventory/${item.id}`} className="card overflow-hidden hover:shadow-md transition-shadow group">
              <div className="aspect-video bg-gray-100 relative">
                {item.thumbnail ? (
                  <img src={getImageUrl(item.thumbnail)} alt={item.name} className="w-full h-full object-cover" />
                ) : (
                  <div className="w-full h-full flex items-center justify-center text-gray-300 text-4xl">📦</div>
                )}
                {item.quantity_on_hand <= item.reorder_level && (
                  <span className="absolute top-2 right-2 badge bg-orange-100 text-orange-800 flex items-center gap-1">
                    <AlertTriangle className="w-3 h-3" /> Low
                  </span>
                )}
              </div>
              <div className="p-4">
                <p className="font-medium text-sm group-hover:text-brand-600">{item.name}</p>
                <p className="text-xs text-gray-500 mt-1">{item.category_name} · Qty: {item.quantity_on_hand}</p>
                <p className="text-sm font-semibold text-brand-600 mt-2">{formatCurrency(item.rental_price)}/rental</p>
              </div>
            </Link>
          ))}
        </div>
      ) : (
        <div className="card overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 border-b">
              <tr>
                <th className="text-left p-3 font-medium">Name</th>
                <th className="text-left p-3 font-medium">SKU</th>
                <th className="text-left p-3 font-medium">Category</th>
                <th className="text-right p-3 font-medium">Qty</th>
                <th className="text-right p-3 font-medium">Rental</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.id} className="border-b hover:bg-gray-50">
                  <td className="p-3"><Link to={`/inventory/${item.id}`} className="text-brand-600 hover:underline">{item.name}</Link></td>
                  <td className="p-3 text-gray-500">{item.sku || '—'}</td>
                  <td className="p-3">{item.category_name || '—'}</td>
                  <td className="p-3 text-right">{item.quantity_on_hand}</td>
                  <td className="p-3 text-right">{formatCurrency(item.rental_price)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
