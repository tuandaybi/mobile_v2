import { create } from 'zustand';

type AnyRec = any;

const clone = <T,>(v: T): T => {
  try { return structuredClone(v); }
  catch { return JSON.parse(JSON.stringify(v)); }
};

// ====== NEW: types cho công nợ ======
type OriginType = 'mobile' | 'service' | 'unknown';

type DebtDrawerSlice = {
  isOpen: boolean;
  customer: AnyRec | null;      // DebtRecord của khách hiện tại
  open: (customer: AnyRec) => void;
  close: () => void;
};

type DebtPaySlice = {
  isOpen: boolean;
  debtId: number | null;
  amount: number | null;
  note: string | null;
  open: (debtId: number, amount?: number | null) => void;
  close: () => void;
  setFields: (patch: Partial<Pick<DebtPaySlice, 'debtId' | 'amount' | 'note'>>) => void;
};

type DebtOriginSlice = {
  isOpen: boolean;
  originType: OriginType;
  originId: number | null;
  debtId: number | null;
  debtRemaining: number | null;
  record: AnyRec | null;        // dữ liệu chi tiết nguồn (mobile-out/service) sau khi fetch
  open: (opt: {
    originType?: OriginType; originId?: number | null;
    debtId?: number | null; debtRemaining?: number | null;
    record?: AnyRec | null;
  }) => void;
  close: () => void;
  setRecord: (rec: AnyRec | null) => void;
};

// ====== slice generic sẵn có ======
type ModalSlice<T = AnyRec> = {
  isOpen: boolean;
  isEdit: boolean;
  record: T | null;
  open: (isEdit?: boolean, record?: T | null) => void;
  close: () => void;
};

type ModalState = {
  mobilesVersion: number;
  servicesVersion: number;
  expensesVersion: number;

  // cũ
  mobile: ModalSlice;
  sellMobile: ModalSlice;
  service: ModalSlice;
  expense: ModalSlice;

  // ====== NEW: công nợ ======
  debtDrawer: DebtDrawerSlice;
  debtPay: DebtPaySlice;
  debtOrigin: DebtOriginSlice;

  bumpMobilesVersion: () => void;
  bumpServicesVersion: () => void;
  bumpExpensesVersion: () => void;
};

export const useModalStore = create<ModalState>((set) => {
  // mobile
  const mobileOpen: ModalSlice['open'] = (isEdit = false, record = null) =>
    set((s) => ({
      mobile: { ...s.mobile, isOpen: true, isEdit, record: record ? clone(record) : null },
    }));
  const mobileClose: ModalSlice['close'] = () =>
    set((s) => ({
      mobile: { ...s.mobile, isOpen: false, isEdit: false, record: null },
    }));

  // sellMobile
  const sellOpen: ModalSlice['open'] = (isEdit = false, record = null) =>
    set((s) => ({
      sellMobile: { ...s.sellMobile, isOpen: true, isEdit, record: record ? clone(record) : null },
    }));
  const sellClose: ModalSlice['close'] = () =>
    set((s) => ({
      sellMobile: { ...s.sellMobile, isOpen: false, isEdit: false, record: null },
    }));

  // service
  const serviceOpen: ModalSlice['open'] = (isEdit = false, record = null) =>
    set((s) => ({
      service: { ...s.service, isOpen: true, isEdit, record: record ? clone(record) : null },
    }));
  const serviceClose: ModalSlice['close'] = () =>
    set((s) => ({
      service: { ...s.service, isOpen: false, isEdit: false, record: null },
    }));

  // expense
  const expenseOpen: ModalSlice['open'] = (isEdit = false, record = null) =>
    set((s) => ({
      expense: { ...s.expense, isOpen: true, isEdit, record: record ? clone(record) : null },
    }));
  const expenseClose: ModalSlice['close'] = () =>
    set((s) => ({
      expense: { ...s.expense, isOpen: false, isEdit: false, record: null },
    }));

  // ====== NEW: debtDrawer ======
  const debtDrawerOpen: DebtDrawerSlice['open'] = (customer) =>
    set((s) => ({
      debtDrawer: { ...s.debtDrawer, isOpen: true, customer: customer ? clone(customer) : null },
    }));
  const debtDrawerClose: DebtDrawerSlice['close'] = () =>
    set((s) => ({
      debtDrawer: { ...s.debtDrawer, isOpen: false, customer: null },
    }));

  // ====== NEW: debtPay ======
  const debtPayOpen: DebtPaySlice['open'] = (debtId, amount = null) =>
    set((s) => ({
      debtPay: { ...s.debtPay, isOpen: true, debtId, amount, note: s.debtPay.note ?? null },
    }));
  const debtPayClose: DebtPaySlice['close'] = () =>
    set((s) => ({
      debtPay: { ...s.debtPay, isOpen: false, debtId: null, amount: null, note: null },
    }));
  const debtPaySetFields: DebtPaySlice['setFields'] = (patch) =>
    set((s) => ({ debtPay: { ...s.debtPay, ...patch } }));

  // ====== NEW: debtOrigin ======
  const debtOriginOpen: DebtOriginSlice['open'] = (opt) =>
    set((s) => ({
      debtOrigin: {
        ...s.debtOrigin,
        isOpen: true,
        originType: opt.originType ?? s.debtOrigin.originType,
        originId: opt.originId ?? s.debtOrigin.originId,
        debtId: opt.debtId ?? s.debtOrigin.debtId,
        debtRemaining: opt.debtRemaining ?? s.debtOrigin.debtRemaining,
        record: opt.record !== undefined ? (opt.record ? clone(opt.record) : null) : s.debtOrigin.record,
      },
    }));
  const debtOriginClose: DebtOriginSlice['close'] = () =>
    set((s) => ({
      debtOrigin: {
        ...s.debtOrigin,
        isOpen: false,
        originType: 'unknown',
        originId: null,
        debtId: null,
        debtRemaining: null,
        record: null,
      },
    }));
  const debtOriginSetRecord: DebtOriginSlice['setRecord'] = (rec) =>
    set((s) => ({ debtOrigin: { ...s.debtOrigin, record: rec ? clone(rec) : null } }));

  return {
    // cũ
    mobile:    { isOpen: false, isEdit: false, record: null, open: mobileOpen,  close: mobileClose  },
    sellMobile:{ isOpen: false, isEdit: false, record: null, open: sellOpen,    close: sellClose    },
    service:   { isOpen: false, isEdit: false, record: null, open: serviceOpen, close: serviceClose },
    expense:   { isOpen: false, isEdit: false, record: null, open: expenseOpen, close: expenseClose },

    // NEW
    debtDrawer: { isOpen: false, customer: null, open: debtDrawerOpen, close: debtDrawerClose },
    debtPay:    { isOpen: false, debtId: null, amount: null, note: null, open: debtPayOpen, close: debtPayClose, setFields: debtPaySetFields },
    debtOrigin: { isOpen: false, originType: 'unknown', originId: null, debtId: null, debtRemaining: null, record: null, open: debtOriginOpen, close: debtOriginClose, setRecord: debtOriginSetRecord },

    mobilesVersion: 0,
    servicesVersion: 0,
    expensesVersion: 0,
    bumpMobilesVersion: () => set(s => ({ mobilesVersion: s.mobilesVersion + 1 })),
    bumpServicesVersion: () => set(s => ({ servicesVersion: s.servicesVersion + 1 })),
    bumpExpensesVersion: () => set(s => ({ expensesVersion: s.expensesVersion + 1 })),
  };
});
