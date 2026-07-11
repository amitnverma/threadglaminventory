import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useState, useEffect } from 'react';
import {
  LayoutDashboard, Package, Calendar, FileText, ShoppingCart,
  TrendingUp, Users, FileSignature, BarChart3, Settings, Search,
  Plus, Menu, X, Sparkles,
} from 'lucide-react';
import api from '@/lib/api';

const navItems = [
  { to: '/', icon: LayoutDashboard, label: 'Dashboard' },
  { to: '/inventory', icon: Package, label: 'Inventory' },
  { to: '/events', icon: Calendar, label: 'Events' },
  { to: '/estimates', icon: FileText, label: 'Estimates' },
  { to: '/purchases', icon: ShoppingCart, label: 'Purchases' },
  { to: '/sales', icon: TrendingUp, label: 'Sales' },
  { to: '/partners', icon: Users, label: 'Partners' },
  { to: '/contracts', icon: FileSignature, label: 'Contracts' },
  { to: '/reports', icon: BarChart3, label: 'Reports' },
  { to: '/settings', icon: Settings, label: 'Settings' },
];

const quickAdd = [
  { to: '/inventory/new', label: 'Inventory Item' },
  { to: '/events/new', label: 'Event' },
  { to: '/estimates/new', label: 'Estimate' },
  { to: '/contracts', label: 'Contract' },
];

export default function AppLayout() {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState(null);
  const [settings, setSettings] = useState({});
  const navigate = useNavigate();

  useEffect(() => {
    api.get('/settings').then(({ data }) => setSettings(data)).catch(() => {});
  }, []);

  useEffect(() => {
    if (searchQuery.length < 2) {
      setSearchResults(null);
      return;
    }
    const timer = setTimeout(() => {
      api.get('/search', { params: { q: searchQuery } }).then(({ data }) => setSearchResults(data));
    }, 300);
    return () => clearTimeout(timer);
  }, [searchQuery]);

  const NavItem = ({ to, icon: Icon, label }) => (
    <NavLink
      to={to}
      end={to === '/'}
      onClick={() => setSidebarOpen(false)}
      className={({ isActive }) =>
        `flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors ${
          isActive ? 'bg-brand-50 text-brand-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
        }`
      }
    >
      <Icon className="w-5 h-5" />
      {label}
    </NavLink>
  );

  return (
    <div className="min-h-screen flex">
      {sidebarOpen && (
        <div className="fixed inset-0 bg-black/50 z-40 lg:hidden" onClick={() => setSidebarOpen(false)} />
      )}

      <aside className={`fixed lg:static inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 flex flex-col transform transition-transform lg:translate-x-0 ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}`}>
        <div className="p-4 border-b border-gray-100">
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 rounded-lg bg-brand-600 flex items-center justify-center">
              <Sparkles className="w-4 h-4 text-white" />
            </div>
            <div>
              <h1 className="font-bold text-gray-900 text-sm">{settings.company_name || 'ThreadGlam'}</h1>
              <p className="text-xs text-gray-500">Event Manager</p>
            </div>
          </div>
        </div>
        <nav className="flex-1 p-3 space-y-1 overflow-y-auto">
          {navItems.map((item) => <NavItem key={item.to} {...item} />)}
        </nav>
      </aside>

      <div className="flex-1 flex flex-col min-w-0">
        <header className="bg-white border-b border-gray-200 px-4 py-3 flex items-center gap-4 sticky top-0 z-30">
          <button className="lg:hidden btn-ghost p-2" onClick={() => setSidebarOpen(true)}>
            <Menu className="w-5 h-5" />
          </button>

          <div className="relative flex-1 max-w-md">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            <input
              type="text"
              placeholder="Search customers, events, inventory..."
              className="input pl-9"
              value={searchQuery}
              onChange={(e) => { setSearchQuery(e.target.value); setSearchOpen(true); }}
              onFocus={() => setSearchOpen(true)}
            />
            {searchOpen && searchResults && (
              <div className="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-50 max-h-80 overflow-y-auto">
                {['customers', 'events', 'inventory'].map((type) =>
                  searchResults[type]?.length > 0 && (
                    <div key={type} className="p-2">
                      <p className="text-xs font-semibold text-gray-400 uppercase px-2 py-1">{type}</p>
                      {searchResults[type].map((item) => (
                        <button
                          key={`${type}-${item.id}`}
                          className="w-full text-left px-2 py-2 text-sm hover:bg-gray-50 rounded"
                          onClick={() => {
                            navigate(`/${type === 'customers' ? 'events' : type}/${item.id}`);
                            setSearchOpen(false);
                            setSearchQuery('');
                          }}
                        >
                          {item.name || item.title}
                        </button>
                      ))}
                    </div>
                  )
                )}
                <button className="w-full text-center py-2 text-xs text-gray-400 hover:text-gray-600" onClick={() => setSearchOpen(false)}>
                  Close
                </button>
              </div>
            )}
          </div>

          <div className="relative group">
            <button className="btn-primary">
              <Plus className="w-4 h-4" /> Quick Add
            </button>
            <div className="absolute right-0 top-full mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
              {quickAdd.map((item) => (
                <button
                  key={item.to}
                  className="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 first:rounded-t-lg last:rounded-b-lg"
                  onClick={() => navigate(item.to)}
                >
                  {item.label}
                </button>
              ))}
            </div>
          </div>
        </header>

        <main className="flex-1 p-4 md:p-6 overflow-auto">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
