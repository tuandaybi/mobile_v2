import { create } from 'zustand';

type AnyRec = any;

const clone = <T,>(v: T): T => {
  try { return structuredClone(v); }
  catch { return JSON.parse(JSON.stringify(v)); }
};

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
  mobile: ModalSlice;
  sellMobile: ModalSlice;
  service: ModalSlice;
  bumpMobilesVersion: () => void;
  bumpServicesVersion: () => void;
};

export const useModalStore = create<ModalState>((set) => {
  // mobile
  const mobileOpen: ModalSlice['open'] = (isEdit = false, record = null) =>
    set((s) => ({
      mobile: {
        ...s.mobile,
        isOpen: true,
        isEdit,
        record: record ? clone(record) : null,
      },
    }));
  const mobileClose: ModalSlice['close'] = () =>
    set((s) => ({
      mobile: {
        ...s.mobile,
        isOpen: false,
        isEdit: false,
        record: null,
      },
    }));

  // sellMobile
  const sellOpen: ModalSlice['open'] = (isEdit = false, record = null) =>
    set((s) => ({
      sellMobile: {
        ...s.sellMobile,
        isOpen: true,
        isEdit,
        record: record ? clone(record) : null,
      },
    }));
  const sellClose: ModalSlice['close'] = () =>
    set((s) => ({
      sellMobile: {
        ...s.sellMobile,
        isOpen: false,
        isEdit: false,
        record: null,
      },
    }));

  // service
  const serviceOpen: ModalSlice['open'] = (isEdit = false, record = null) =>
    set((s) => ({
      service: {
        ...s.service,
        isOpen: true,
        isEdit,
        record: record ? clone(record) : null,
      },
    }));
  const serviceClose: ModalSlice['close'] = () =>
    set((s) => ({
      service: {
        ...s.service,
        isOpen: false,
        isEdit: false,
        record: null,
      },
    }));

  return {
    mobile:    { isOpen: false, isEdit: false, record: null, open: mobileOpen, close: mobileClose },
    sellMobile:{ isOpen: false, isEdit: false, record: null, open: sellOpen,   close: sellClose   },
    service:   { isOpen: false, isEdit: false, record: null, open: serviceOpen,close: serviceClose},
    mobilesVersion: 0,
    servicesVersion: 0,
    bumpMobilesVersion: () => set(s => ({ mobilesVersion: s.mobilesVersion + 1 })),
    bumpServicesVersion: () => set(s => ({ servicesVersion: s.servicesVersion + 1 })),
  };
});
