import { Routes, Route } from 'react-router-dom';
import AppLayout from './components/Layout/AppLayout';
import Dashboard from './pages/Dashboard';
import InventoryIndex from './pages/Inventory/Index';
import InventoryForm from './pages/Inventory/Form';
import InventoryShow from './pages/Inventory/Show';
import EventsIndex from './pages/Events/Index';
import EventForm from './pages/Events/Form';
import EventShow from './pages/Events/Show';
import EstimatesIndex from './pages/Estimates/Index';
import EstimateBuilder from './pages/Estimates/Builder';
import PurchasesIndex from './pages/Finance/Purchases';
import SalesIndex from './pages/Finance/Sales';
import PartnersIndex from './pages/Partners/Index';
import ContractsIndex from './pages/Contracts/Index';
import ContractEditor from './pages/Contracts/Editor';
import ReportsIndex from './pages/Reports/Index';
import SettingsPage from './pages/Settings';

export default function App() {
  return (
    <Routes>
      <Route element={<AppLayout />}>
        <Route index element={<Dashboard />} />
        <Route path="inventory" element={<InventoryIndex />} />
        <Route path="inventory/new" element={<InventoryForm />} />
        <Route path="inventory/:id" element={<InventoryShow />} />
        <Route path="inventory/:id/edit" element={<InventoryForm />} />
        <Route path="events" element={<EventsIndex />} />
        <Route path="events/new" element={<EventForm />} />
        <Route path="events/:id" element={<EventShow />} />
        <Route path="events/:id/edit" element={<EventForm />} />
        <Route path="estimates" element={<EstimatesIndex />} />
        <Route path="estimates/new" element={<EstimateBuilder />} />
        <Route path="estimates/:id" element={<EstimateBuilder />} />
        <Route path="purchases" element={<PurchasesIndex />} />
        <Route path="sales" element={<SalesIndex />} />
        <Route path="partners" element={<PartnersIndex />} />
        <Route path="contracts" element={<ContractsIndex />} />
        <Route path="contracts/:id" element={<ContractEditor />} />
        <Route path="reports" element={<ReportsIndex />} />
        <Route path="settings" element={<SettingsPage />} />
      </Route>
    </Routes>
  );
}
