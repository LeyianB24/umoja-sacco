'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { formatDate } from '@/lib/utils';
import { ShieldCheck, UserPlus, Users } from 'lucide-react';

export default function AdminUsersPage() {
  const [users, setUsers] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchUsers = async () => {
      try {
        const res = await api.get('/admin/users');
        if (res.status === 'success') {
          setUsers(res.data.users || []);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchUsers();
  }, []);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>System Administrators & Staff</h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Manage administrative accounts, assigned role matrix, and login activity</p>
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Username</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Assigned Role</th>
                <th>Status</th>
                <th>Last Login</th>
              </tr>
            </thead>
            <tbody>
              {users.length ? (
                users.map((u, i) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 800, fontFamily: 'monospace' }}>{u.username}</td>
                    <td style={{ fontWeight: 600 }}>{u.full_name}</td>
                    <td>{u.email}</td>
                    <td><span className="badge badge-lime">{u.role_name}</span></td>
                    <td><span className="badge badge-success">{u.is_active ? 'Active' : 'Disabled'}</span></td>
                    <td style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>{u.last_login ? formatDate(u.last_login) : 'Never'}</td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={6} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    {loading ? 'Loading system users...' : 'No admin users found.'}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
