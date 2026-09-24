'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Shield, Check, X } from 'lucide-react';

export default function AdminRolesPage() {
  const [roles, setRoles] = useState<any[]>([]);
  const [permissions, setPermissions] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchRoles = async () => {
      try {
        const res = await api.get('/admin/roles');
        if (res.status === 'success') {
          setRoles(res.data.roles || []);
          setPermissions(res.data.all_permissions || []);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchRoles();
  }, []);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Access Control & Role Matrix (RBAC)</h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Granular module permissions and security privilege assignments across Sacco roles</p>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '20px' }}>
        {roles.map((r, i) => (
          <div key={i} className="card">
            <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '10px' }}>
              <Shield size={20} color="var(--brand-forest)" />
              <h3 style={{ fontSize: '1.1rem', fontWeight: 800 }}>{r.name}</h3>
            </div>
            <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)', marginBottom: '16px' }}>
              {r.description || 'System access role definition'}
            </p>
            <div style={{ borderTop: '1px solid var(--border-color)', paddingTop: '12px' }}>
              <div style={{ fontSize: '0.75rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--text-dim)', marginBottom: '8px' }}>
                Granted Capabilities:
              </div>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px' }}>
                {r.id === 1 ? (
                  <span className="badge badge-lime">Full Superadmin Access</span>
                ) : r.permissions?.length ? (
                  r.permissions.map((p: string, pIdx: number) => (
                    <span key={pIdx} className="badge badge-info" style={{ fontSize: '0.7rem' }}>
                      {p.replace('.php', '')}
                    </span>
                  ))
                ) : (
                  <span style={{ fontSize: '0.78rem', color: 'var(--text-muted)' }}>Standard viewer access</span>
                )}
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
