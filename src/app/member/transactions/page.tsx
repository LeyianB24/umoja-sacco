'use client';

import React, { useEffect, useState, useMemo } from 'react';
import { api } from '@/lib/api';
import { formatKES, formatDate } from '@/lib/utils';
import {
  Button,
  Card,
  Input,
  Badge,
  ListItem,
} from '@/components/sacco-ui';
import {
  ArrowDownRight,
  ArrowUpRight,
  Search,
  ChevronLeft,
  ChevronRight,
  Filter,
} from 'lucide-react';

export default function MemberTransactionsPage() {
  const [transactions, setTransactions] = useState<any[]>([]);
  const [pagination, setPagination] = useState<any>(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [filterType, setFilterType] = useState<string>('all');

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

  const filteredTransactions = useMemo(() => {
    return transactions.filter((tx) => {
      const notes = (tx.notes || tx.description || '').toLowerCase();
      const ref = (tx.reference || '').toLowerCase();
      const type = (tx.action_type || tx.transaction_type || '').toLowerCase();
      const query = searchQuery.toLowerCase();

      const matchesSearch = notes.includes(query) || ref.includes(query) || type.includes(query);
      if (!matchesSearch) return false;

      if (filterType === 'credits') {
        return ['deposit', 'contribution', 'dividend', 'loan_disbursement'].includes(type);
      }
      if (filterType === 'debits') {
        return ['withdrawal', 'loan_repayment', 'fee', 'welfare_claim'].includes(type);
      }
      return true;
    });
  }, [transactions, searchQuery, filterType]);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
      {/* Header */}
      <div>
        <h1 className="heading-1" style={{ margin: 0 }}>Transaction Ledger</h1>
        <p className="body-rg" style={{ margin: '4px 0 0 0' }}>
          Complete, immutable financial audit history of all inflows and outflows
        </p>
      </div>

      {/* Search & Filter Toolbar */}
      <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap', alignItems: 'center' }}>
        <div style={{ flex: 1, minWidth: '240px' }}>
          <Input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Search by notes, reference code, or action..."
          />
        </div>

        <div style={{ display: 'flex', gap: '6px' }}>
          <Button
            variant={filterType === 'all' ? 'primary' : 'secondary'}
            size="md"
            pill
            onClick={() => setFilterType('all')}
          >
            All
          </Button>
          <Button
            variant={filterType === 'credits' ? 'primary' : 'secondary'}
            size="md"
            pill
            onClick={() => setFilterType('credits')}
          >
            Inflows (+)
          </Button>
          <Button
            variant={filterType === 'debits' ? 'primary' : 'secondary'}
            size="md"
            pill
            onClick={() => setFilterType('debits')}
          >
            Outflows (-)
          </Button>
        </div>
      </div>

      {/* Transaction List */}
      <Card variant="default">
        <Card.Header>
          <h3 className="heading-2" style={{ fontSize: '18px', margin: 0 }}>
            {filterType === 'all' ? 'All Transactions' : filterType === 'credits' ? 'Inflows (Credits)' : 'Outflows (Debits)'}
          </h3>
          <span style={{ fontSize: '12px', color: 'var(--color-gray-medium)' }}>
            Showing {filteredTransactions.length} records
          </span>
        </Card.Header>
        <Card.Body>
          {loading ? (
            <div style={{ textAlign: 'center', padding: '40px', color: 'var(--color-gray-medium)' }}>
              Loading ledger...
            </div>
          ) : filteredTransactions.length > 0 ? (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
              {filteredTransactions.map((tx: any, idx: number) => {
                const type = tx.action_type || tx.transaction_type || 'transaction';
                const isCredit = ['deposit', 'contribution', 'dividend', 'loan_disbursement'].includes(type.toLowerCase());
                return (
                  <ListItem
                    key={idx}
                    icon={isCredit ? <ArrowDownRight size={18} color="#16a34a" /> : <ArrowUpRight size={18} color="#dc2626" />}
                    label={tx.notes || tx.description || type.replace('_', ' ')}
                    time={`${formatDate(tx.transaction_date || tx.created_at)} • ${tx.payment_method || 'M-Pesa'} • Ref: ${tx.reference || 'N/A'}`}
                    amount={`${isCredit ? '+' : '-'}${formatKES(tx.amount)}`}
                    type={isCredit ? 'credit' : 'debit'}
                  />
                );
              })}
            </div>
          ) : (
            <div style={{ textAlign: 'center', padding: '40px', color: 'var(--color-gray-medium)', fontSize: '14px' }}>
              No transactions match your search or filter.
            </div>
          )}
        </Card.Body>

        {/* Pagination */}
        {pagination && pagination.total_pages > 1 && (
          <Card.Footer>
            <span style={{ fontSize: '13px', color: 'var(--color-gray-dark)' }}>
              Page {pagination.current_page} of {pagination.total_pages} ({pagination.total} total)
            </span>
            <div style={{ display: 'flex', gap: '8px' }}>
              <Button
                variant="secondary"
                size="sm"
                disabled={page <= 1}
                onClick={() => setPage(page - 1)}
              >
                <ChevronLeft size={14} /> Prev
              </Button>
              <Button
                variant="secondary"
                size="sm"
                disabled={page >= pagination.total_pages}
                onClick={() => setPage(page + 1)}
              >
                Next <ChevronRight size={14} />
              </Button>
            </div>
          </Card.Footer>
        )}
      </Card>
    </div>
  );
}
