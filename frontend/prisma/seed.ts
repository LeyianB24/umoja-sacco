import { PrismaClient } from '@prisma/client';
import bcrypt from 'bcryptjs';

const prisma = new PrismaClient();

async function main() {
  console.log('🌱 Starting Umoja SACCO Database Seeding...');

  // 1. Seed Roles
  await prisma.roles.upsert({
    where: { id: 1 },
    update: {},
    create: {
      id: 1,
      name: 'superadmin',
      description: 'Full system control and administrative access',
    },
  });

  await prisma.roles.upsert({
    where: { id: 2 },
    update: {},
    create: {
      id: 2,
      name: 'manager',
      description: 'Operations and Branch Manager',
    },
  });

  await prisma.roles.upsert({
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
  const adminPassword = await bcrypt.hash('admin123', 10);
  await prisma.admins.upsert({
    where: { admin_id: 1 },
    update: {},
    create: {
      admin_id: 1,
      username: 'superadmin',
      full_name: 'System Administrator',
      email: 'admin@umojasacco.co.ke',
      password: adminPassword,
      role_id: 1,
      phone: '+254700000000',
    },
  });

  console.log('✅ Superadmin user seeded (admin@umojasacco.co.ke / admin123)');

  // 3. Seed Default Member
  const memberPassword = await bcrypt.hash('password123', 10);
  await prisma.members.upsert({
    where: { member_id: 1 },
    update: {},
    create: {
      member_id: 1,
      member_reg_no: 'UDS-2025-0001',
      full_name: 'Bezalel Leyian',
      email: 'leyianbeza24@gmail.com',
      phone: '+254796157265',
      national_id: '32001122',
      password: memberPassword,
      status: 'active',
      kyc_status: 'verified',
      gender: 'male',
      occupation: 'Transport Operator',
      join_date: new Date(),
    },
  });

  console.log('✅ Default member seeded (leyianbeza24@gmail.com / password123)');

  // 4. Seed Core Ledger Accounts
  const coreAccounts = [
    { id: 1, name: 'Cash at Bank - Operating', type: 'Asset', category: 'bank' },
    { id: 2, name: 'M-Pesa Paybill Clearing', type: 'Asset', category: 'mpesa' },
    { id: 3, name: 'Member Loan Portfolio', type: 'Asset', category: 'loans' },
    { id: 4, name: 'Member Regular Savings', type: 'Liability', category: 'savings' },
    { id: 5, name: 'Member Welfare Fund', type: 'Liability', category: 'welfare' },
    { id: 6, name: 'Member Share Capital', type: 'Equity', category: 'shares' },
    { id: 7, name: 'Loan Interest Income', type: 'Income', category: 'revenue' },
    { id: 8, name: 'Registration & Processing Fees', type: 'Income', category: 'revenue' },
    { id: 9, name: 'Administrative Expenses', type: 'Expense', category: 'expenses' },
  ];

  for (const acc of coreAccounts) {
    await prisma.ledgerAccounts.upsert({
      where: { account_id: acc.id },
      update: {},
      create: {
        account_id: acc.id,
        account_name: acc.name,
        account_type: acc.type,
        category: acc.category,
        current_balance: 0,
        status: 'active',
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
