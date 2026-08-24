'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { formatKES, formatDate } from '@/lib/utils';
import { ArrowLeftRight, ArrowDownRight, ArrowUpRight, ChevronLeft, ChevronRight } from 'lucide-react';

export default function MemberTransactionsPage() {
  const [transactions, setTransactions] = useState<any[]>([]);
  const [pagination, setPagination] = useState<any>(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);

  const fetchTxns = async (p = 1) => {
    setLoading(true);
    try {
      const res = await api.get('/member/transactions', { page: p, limit: 20 });
      if (res.status === 'success') {
        setTransactions(res.data.transactions || []);
        setPagination(res.data.pagination);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTxns(page);
  }, [page]);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Transaction Ledger</h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>Complete, immutable financial audit history of all inflows and outflows</p>
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Action / Type</th>
                <th>Amount</th>
                <th>Payment Method</th>
                <th>Reference Code</th>
                <th>Notes / Remarks</th>
                <th>Timestamp</th>
              </tr>
            </thead>
            <tbody>
              {transactions.length ? (
                transactions.map((tx, i) => {
                  const type = tx.action_type || tx.transaction_type || 'transaction';
                  const isCredit = ['deposit', 'contribution', 'dividend', 'loan_disbursement'].includes(type.toLowerCase());
                  return (
                    <tr key={i}>
                      <td style={{ fontWeight: 700, textTransform: 'capitalize' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                          {isCredit ? <ArrowDownRight size={16} color="#16a34a" /> : <ArrowUpRight size={16} color="#dc2626" />}
                          {type.replace('_', ' ')}
                        </div>
                      </td>
                      <td style={{ fontWeight: 800, color: isCredit ? '#16a34a' : 'var(--text-main)' }}>
                        {isCredit ? '+' : '-'}{formatKES(tx.amount)}
                      </td>
                      <td style={{ textTransform: 'uppercase', fontSize: '0.82rem' }}>{tx.method || 'M-Pesa'}</td>
                      <td>
                        <span style={{ fontFamily: 'monospace', fontSize: '0.8rem', backgroundColor: 'var(--surface-2)', padding: '2px 6px', borderRadius: '4px' }}>
                          {tx.reference || 'N/A'}
                        </span>
                      </td>
                      <td style={{ color: 'var(--text-muted)', fontSize: '0.85rem' }}>{tx.notes || '-'}</td>
                      <td style={{ color: 'var(--text-muted)', fontSize: '0.82rem' }}>{formatDate(tx.created_at)}</td>
                    </tr>
                  );
                })
              ) : (
                <tr>
                  <td colSpan={6} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    {loading ? 'Loading transactions...' : 'No transactions recorded yet.'}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {pagination && pagination.total_pages > 1 && (
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '16px 24px', borderTop: '1px solid var(--border-color)' }}>
            <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>
              Page {pagination.current_page} of {pagination.total_pages} ({pagination.total} total)
            </span>
            <div style={{ display: 'flex', gap: '8px' }}>
              <button
                disabled={page <= 1}
                onClick={() => setPage(page - 1)}
                className="btn btn-sm btn-ghost"
              >
                <ChevronLeft size={16} /> Prev
              </button>
              <button
                disabled={page >= pagination.total_pages}
                onClick={() => setPage(page + 1)}
                className="btn btn-sm btn-ghost"
              >
                Next <ChevronRight size={16} />
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
