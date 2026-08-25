import { PrismaClient } from '@prisma/client';
import bcrypt from 'bcryptjs';

const prisma = new PrismaClient();

async function main() {
  console.log('🌱 Starting Umoja SACCO Database Seeding...');

  // 1. Seed Roles
  await prisma.roles.upsert({
    where: { id: 1 },
    update: { name: 'superadmin', description: 'Full system control and administrative access' },
    create: {
      id: 1,
      name: 'superadmin',
      description: 'Full system control and administrative access',
    },
  });

  await prisma.roles.upsert({
    where: { id: 2 },
    update: { name: 'manager', description: 'Operations and Branch Manager' },
    create: {
      id: 2,
      name: 'manager',
      description: 'Operations and Branch Manager',
    },
  });

  await prisma.roles.upsert({
    where: { id: 3 },
    update: { name: 'accountant', description: 'Financial accounting and reconciliation' },
    create: {
      id: 3,
      name: 'accountant',
      description: 'Financial accounting and reconciliation',
    },
  });

  await prisma.roles.upsert({
    where: { id: 4 },
    update: { name: 'admin', description: 'IT Administrator & System Support' },
    create: {
      id: 4,
      name: 'admin',
      description: 'IT Administrator & System Support',
    },
  });

  console.log('✅ Roles seeded (superadmin, manager, accountant, admin)');

  // 2. Hash Passwords
  const superadminPwd = await bcrypt.hash('admin123', 10);
  const accountantPwd = await bcrypt.hash('accountant123', 10);
  const managerPwd = await bcrypt.hash('manager123', 10);
  const adminItPwd = await bcrypt.hash('adminIT123', 10);

  // 2.1 superadmin (superadmin / admin123)
  await prisma.admins.upsert({
    where: { username: 'superadmin' },
    update: {
      full_name: 'Super Administrator',
      email: 'superadmin@umojasacco.co.ke',
      password: superadminPwd,
      role_id: 1,
    },
    create: {
      username: 'superadmin',
      full_name: 'Super Administrator',
      email: 'superadmin@umojasacco.co.ke',
      password: superadminPwd,
      role_id: 1,
      phone: '+254700000001',
    },
  });
  console.log('✅ 1. superadmin seeded (superadmin / admin123)');

  // 2.2 accountant (accountant / accountant123)
  await prisma.admins.upsert({
    where: { username: 'accountant' },
    update: {
      full_name: 'Senior Accountant',
      email: 'accountant@umojasacco.co.ke',
      password: accountantPwd,
      role_id: 3,
    },
    create: {
      username: 'accountant',
      full_name: 'Senior Accountant',
      email: 'accountant@umojasacco.co.ke',
      password: accountantPwd,
      role_id: 3,
      phone: '+254700000002',
    },
  });
  console.log('✅ 2. accountant seeded (accountant / accountant123)');

  // 2.3 manager (manager / manager123)
  await prisma.admins.upsert({
    where: { username: 'manager' },
    update: {
      full_name: 'Operations Manager',
      email: 'manager@umojasacco.co.ke',
      password: managerPwd,
      role_id: 2,
    },
    create: {
      username: 'manager',
      full_name: 'Operations Manager',
      email: 'manager@umojasacco.co.ke',
      password: managerPwd,
      role_id: 2,
      phone: '+254700000003',
    },
  });
  console.log('✅ 3. manager seeded (manager / manager123)');

  // 2.4 Admin (Admin / adminIT123)
  await prisma.admins.upsert({
    where: { username: 'Admin' },
    update: {
      full_name: 'IT Administrator',
      email: 'admin@umojasacco.co.ke',
      password: adminItPwd,
      role_id: 4,
    },
    create: {
      username: 'Admin',
      full_name: 'IT Administrator',
      email: 'admin@umojasacco.co.ke',
      password: adminItPwd,
      role_id: 4,
      phone: '+254700000004',
    },
  });
  console.log('✅ 4. Admin seeded (Admin / adminIT123)');

  // 3. Seed Default Members
  const memberPassword = await bcrypt.hash('password123', 10);
  await prisma.members.upsert({
    where: { email: 'leyianbeza24@gmail.com' },
    update: {
      password: memberPassword,
    },
    create: {
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
  console.log('✅ Member seeded (leyianbeza24@gmail.com / password123)');

  // 3.1 Bezalel Tomaka (Superadmin & Full Member)
  const bezalelPwd = await bcrypt.hash('Ley254bez@', 10);
  await prisma.admins.upsert({
    where: { email: 'bezaleltomaka@gmail.com' },
    update: {
      full_name: 'Bezalel Tomaka',
      password: bezalelPwd,
      role_id: 1,
      phone: '+254712345678',
    },
    create: {
      username: 'bezaleltomaka',
      full_name: 'Bezalel Tomaka',
      email: 'bezaleltomaka@gmail.com',
      password: bezalelPwd,
      role_id: 1,
      phone: '+254712345678',
    },
  });
  console.log('✅ Superadmin seeded (bezaleltomaka@gmail.com / Ley254bez@)');

  await prisma.members.upsert({
    where: { email: 'bezaleltomaka@gmail.com' },
    update: {
      full_name: 'Bezalel Tomaka',
      password: bezalelPwd,
      status: 'active',
      kyc_status: 'verified',
    },
    create: {
      member_reg_no: 'UDS-2025-0002',
      full_name: 'Bezalel Tomaka',
      email: 'bezaleltomaka@gmail.com',
      phone: '+254712345678',
      national_id: '34567890',
      password: bezalelPwd,
      status: 'active',
      kyc_status: 'verified',
      gender: 'male',
      occupation: 'Fleet Director / Transport Specialist',
      address: 'Nairobi Central, Kenya',
      next_of_kin_name: 'Grace Tomaka',
      next_of_kin_phone: '+254722998877',
      join_date: new Date(),
    },
  });
  console.log('✅ Member seeded (bezaleltomaka@gmail.com / Ley254bez@)');

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
