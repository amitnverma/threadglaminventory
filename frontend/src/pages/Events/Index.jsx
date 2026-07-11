import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Search, Archive } from 'lucide-react';
import api from '@/lib/api';
import PageHeader, { EmptyState, StatusBadge } from '@/components/ui/PageHeader';
import { EVENT_STATUSES, formatDate } from '@/lib/utils';

const STATUS_STEPS = ['inquiry', 'estimated', 'confirmed', 'completed'];

export default function EventsIndex() {
  const [events, setEvents] = useState([]);
  const [search, setSearch] = useState('');
  const [showArchived, setShowArchived] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    api.get('/events', { params: { search, archived: showArchived } })
      .then(({ data }) => setEvents(data.data))
      .finally(() => setLoading(false));
  }, [search, showArchived]);

  return (
    <div>
      <PageHeader
        title="Events"
        subtitle="Manage customer events and ceremonies"
        action={<Link to="/events/new" className="btn-primary"><Plus className="w-4 h-4" /> New Event</Link>}
      />

      <div className="card p-4 mb-4 flex gap-3">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input className="input pl-9" placeholder="Search events..." value={search} onChange={(e) => setSearch(e.target.value)} />
        </div>
        <button className={`btn-secondary ${showArchived ? 'bg-brand-50' : ''}`} onClick={() => setShowArchived(!showArchived)}>
          <Archive className="w-4 h-4" /> Archived
        </button>
      </div>

      {loading ? (
        <div className="space-y-3">{[1,2,3].map(i => <div key={i} className="h-20 bg-gray-200 rounded-xl animate-pulse" />)}</div>
      ) : events.length === 0 ? (
        <EmptyState title="No events yet" description="Create your first event to start managing ceremonies." action={<Link to="/events/new" className="btn-primary">Create Event</Link>} />
      ) : (
        <div className="space-y-3">
          {events.map((event) => (
            <Link key={event.id} to={`/events/${event.id}`} className="card p-4 hover:shadow-md transition-shadow block">
              <div className="flex items-start justify-between gap-4">
                <div className="flex-1">
                  <div className="flex items-center gap-3 mb-1">
                    <h3 className="font-semibold">{event.title}</h3>
                    <StatusBadge status={event.status} list={EVENT_STATUSES} />
                  </div>
                  <p className="text-sm text-gray-500">{event.customer_name} · {formatDate(event.event_date)} · {event.venue || 'No venue'}</p>
                  <div className="flex items-center gap-1 mt-3">
                    {STATUS_STEPS.map((step, i) => (
                      <div key={step} className="flex items-center">
                        <div className={`w-2.5 h-2.5 rounded-full ${STATUS_STEPS.indexOf(event.status) >= i ? 'bg-brand-500' : 'bg-gray-200'}`} />
                        {i < STATUS_STEPS.length - 1 && <div className={`w-8 h-0.5 ${STATUS_STEPS.indexOf(event.status) > i ? 'bg-brand-500' : 'bg-gray-200'}`} />}
                      </div>
                    ))}
                  </div>
                </div>
                <div className="text-right text-sm text-gray-400">
                  <p>{event.estimate_count} estimates</p>
                </div>
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
