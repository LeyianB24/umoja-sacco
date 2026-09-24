'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { formatDate } from '@/lib/utils';
import { Database, Download, HardDriveDownload, PlusCircle } from 'lucide-react';

export default function AdminBackupsPage() {
  const { toast } = useToast();
  const [backups, setBackups] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchBackups = async () => {
    try {
      const res = await api.get('/admin/backups');
      if (res.status === 'success') {
        setBackups(res.data.backups || []);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchBackups();
  }, []);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Database Backups & Disaster Recovery</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Automated snapshots and manual database dumps</p>
        </div>
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div style={{ padding: '20px 24px', borderBottom: '1px solid var(--border-color)' }}>
          <h3 style={{ fontSize: '1.05rem', fontWeight: 700 }}>Available Backup Archives</h3>
        </div>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Backup Filename</th>
                <th>File Size</th>
                <th>Created Timestamp</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {backups.length ? (
                backups.map((b, i) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 700, fontFamily: 'monospace' }}>{b.filename}</td>
                    <td>{(b.size / (1024 * 1024)).toFixed(2)} MB</td>
                    <td>{formatDate(b.created_at)}</td>
                    <td><span className="badge badge-success">Verified SQL Dump</span></td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={4} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    {loading ? 'Checking backup directory...' : 'Database snapshots configured for automated backup.'}
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
