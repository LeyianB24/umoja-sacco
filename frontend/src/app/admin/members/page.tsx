'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { formatKES, formatDate } from '@/lib/utils';
import { Users, UserPlus, Search, Filter, ChevronLeft, ChevronRight, Eye } from 'lucide-react';

export default function AdminMembersPage() {
  const [members, setMembers] = useState<any[]>([]);
  const [pagination, setPagination] = useState<any>(null);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);

  const fetchMembers = async (p = 1) => {
    setLoading(true);
    try {
      const res = await api.get('/admin/members', {
        search,
        status,
        page: p,
        limit: 20,
      });
      if (res.status === 'success') {
        setMembers(res.data.members || []);
        setPagination(res.data.pagination);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchMembers(page);
  }, [page, status]);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    setPage(1);
    fetchMembers(1);
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Members Directory</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Search, filter, verify KYC, and manage all registered Sacco members</p>
        </div>
        <Link href="/admin/members/onboarding" className="btn btn-lime">
          <UserPlus size={16} /> Onboard New Member
        </Link>
      </div>

      {/* Filters Bar */}
      <div className="card" style={{ padding: '16px 20px' }}>
        <form onSubmit={handleSearch} style={{ display: 'flex', flexWrap: 'wrap', gap: '14px', alignItems: 'center' }}>
          <div style={{ flex: 1, minWidth: '240px', position: 'relative' }}>
            <input
              type="text"
              className="input-control"
              placeholder="Search by name, reg number, ID, phone, or email..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <select
            className="input-control"
            style={{ width: 'auto', minWidth: '160px' }}
            value={status}
            onChange={(e) => setStatus(e.target.value)}
          >
            <option value="">All Statuses</option>
            <option value="active">Active Members</option>
            <option value="inactive">Inactive</option>
            <option value="suspended">Suspended</option>
            <option value="exited">Exited</option>
          </select>
          <button type="submit" className="btn btn-forest">
            <Search size={16} /> Search
          </button>
        </form>
      </div>

      {/* Members Table */}
      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Reg No</th>
                <th>Full Name</th>
                <th>National ID</th>
                <th>Phone Number</th>
                <th>Savings Balance</th>
                <th>KYC Status</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {members.length ? (
                members.map((m, i) => (
                  <tr key={i}>
                    <td>
                      <span style={{ fontFamily: 'monospace', fontWeight: 800, color: 'var(--brand-forest)' }}>
                        {m.member_reg_no}
                      </span>
                    </td>
                    <td style={{ fontWeight: 700 }}>{m.full_name}</td>
                    <td>{m.national_id}</td>
                    <td>{m.phone}</td>
                    <td style={{ fontWeight: 700 }}>{formatKES(m.savings_balance)}</td>
                    <td>
                      <span className={`badge ${m.kyc_status === 'approved' ? 'badge-success' : 'badge-warning'}`}>
                        {m.kyc_status || 'pending'}
                      </span>
                    </td>
                    <td>
                      <span className={`badge ${m.status === 'active' ? 'badge-success' : 'badge-danger'}`}>
                        {m.status}
                      </span>
                    </td>
                    <td>
                      <Link
                        href={`/admin/members/${m.member_id}`}
                        className="btn btn-sm btn-outline-forest"
                        style={{ padding: '4px 10px', fontSize: '0.78rem' }}
                      >
                        <Eye size={14} /> View Profile
                      </Link>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={8} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    {loading ? 'Loading members directory...' : 'No members found matching your search.'}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {pagination && pagination.total_pages > 1 && (
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '16px 24px', borderTop: '1px solid var(--border-color)' }}>
            <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>
              Page {pagination.current_page} of {pagination.total_pages} ({pagination.total} total members)
            </span>
            <div style={{ display: 'flex', gap: '8px' }}>
              <button disabled={page <= 1} onClick={() => setPage(page - 1)} className="btn btn-sm btn-ghost">
                <ChevronLeft size={16} /> Prev
              </button>
              <button disabled={page >= pagination.total_pages} onClick={() => setPage(page + 1)} className="btn btn-sm btn-ghost">
                Next <ChevronRight size={16} />
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
