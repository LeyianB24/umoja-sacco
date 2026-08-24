import { PrismaClient } from '@prisma/client';
import bcrypt from 'bcryptjs';

const prisma = new PrismaClient();

async function main() {
  console.log('🌱 Starting Umoja SACCO Database Seeding...');

  // 1. Seed Roles
  const superadminRole = await prisma.roles.upsert({
    where: { id: 1 },
    update: {},
    create: {
      id: 1,
      name: 'superadmin',
      description: 'Full system control and administrative access',
    },
  });

  const managerRole = await prisma.roles.upsert({
    where: { id: 2 },
    update: {},
    create: {
      id: 2,
      name: 'manager',
      description: 'Operations and Branch Manager',
    },
  });

  const accountantRole = await prisma.roles.upsert({
    where: { id: 3 },
    update: {},
    create: {
      id: 3,
      name: 'accountant',
      description: 'Financial accounting and reconciliation',
    },
  });

  console.log('✅ Roles seeded');

  // 2. Seed Default Superadmin
  const hashedPassword = await bcrypt.hash('admin123', 10);
  await prisma.admins.upsert({
    where: { admin_id: 1 },
    update: {},
    create: {
      admin_id: 1,
      username: 'superadmin',
      full_name: 'System Administrator',
      email: 'admin@umojasacco.co.ke',
      password: hashedPassword,
      role_id: 1,
      is_active: 1,
      phone: '+254700000000',
    },
  });

  console.log('✅ Superadmin user seeded (admin@umojasacco.co.ke / admin123)');

  // 3. Seed Core Ledger Accounts
  const coreAccounts = [
    { code: '1000', name: 'Cash at Bank - Operating', type: 'Asset', sub: 'Current Asset' },
    { code: '1010', name: 'M-Pesa Paybill Clearing', type: 'Asset', sub: 'Current Asset' },
    { code: '1200', name: 'Member Loan Portfolio', type: 'Asset', sub: 'Loan Asset' },
    { code: '2000', name: 'Member Regular Savings', type: 'Liability', sub: 'Member Deposits' },
    { code: '2010', name: 'Member Welfare Fund', type: 'Liability', sub: 'Welfare' },
    { code: '3000', name: 'Member Share Capital', type: 'Equity', sub: 'Share Capital' },
    { code: '4000', name: 'Loan Interest Income', type: 'Income', sub: 'Financial Income' },
    { code: '4010', name: 'Registration & Processing Fees', type: 'Income', sub: 'Fees' },
    { code: '5000', name: 'Administrative Expenses', type: 'Expense', sub: 'Operating Expense' },
  ];

  for (const acc of coreAccounts) {
    await prisma.ledgerAccounts.upsert({
      where: { account_code: acc.code },
      update: {},
      create: {
        account_code: acc.code,
        account_name: acc.name,
        account_type: acc.type,
        sub_account_type: acc.sub,
        balance: 0,
        is_active: true,
      },
    });
  }

  console.log('✅ Chart of Accounts seeded');
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
