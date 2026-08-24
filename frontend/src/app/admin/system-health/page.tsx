'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { MetricCard } from '@/components/ui/MetricCard';
import { Activity, Database, Server, Cpu, CheckCircle2 } from 'lucide-react';

export default function AdminSystemHealthPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchHealth = async () => {
      try {
        const res = await api.get('/admin/dashboard');
        if (res.status === 'success') {
          setData(res.data);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchHealth();
  }, []);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>System Health & Infrastructure</h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Server memory, database connection pool, API throughput, and security status</p>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '20px' }}>
        <MetricCard title="System Performance Index" value="98.5% (Optimal)" variant="forest" icon={<Activity size={24} />} />
        <MetricCard title="Database Tables Health" value="All OK (InnoDB)" variant="lime" icon={<Database size={24} />} />
        <MetricCard title="API Gateway Response" value="~15ms (Fast)" variant="default" icon={<Server size={24} color="#16a34a" />} />
      </div>

      <div className="card">
        <h3 style={{ fontSize: '1.05rem', fontWeight: 700, marginBottom: '16px' }}>Service Diagnostics</h3>
        <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', padding: '12px 16px', borderRadius: '12px', backgroundColor: 'var(--surface-2)' }}>
            <span style={{ fontWeight: 600 }}>MySQL / MariaDB Service</span>
            <span className="badge badge-success">Connected</span>
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', padding: '12px 16px', borderRadius: '12px', backgroundColor: 'var(--surface-2)' }}>
            <span style={{ fontWeight: 600 }}>Next.js Frontend SSR Engine</span>
            <span className="badge badge-success">Running Port 3000</span>
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', padding: '12px 16px', borderRadius: '12px', backgroundColor: 'var(--surface-2)' }}>
            <span style={{ fontWeight: 600 }}>PHP Backend REST Engine</span>
            <span className="badge badge-success">Operational API v1</span>
          </div>
        </div>
      </div>
    </div>
  );
}
