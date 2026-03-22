import React, { createContext, useContext, useState, useEffect } from 'react';

const LayoutContext = createContext(null);

export function LayoutProvider({ children }) {
  const [menuSize, setMenuSize]         = useState('default');
  const [showBackdrop, setShowBackdrop] = useState(false);

  useEffect(() => {
    document.documentElement.setAttribute('data-sidenav-size', menuSize);
    document.documentElement.setAttribute('data-menu-color',   'brand');
    document.documentElement.setAttribute('data-topbar-color', 'light');
  }, [menuSize]);

  function changeMenuSize(size) { setMenuSize(size); }

  function toggleBackdrop() {
    setShowBackdrop(prev => {
      if (prev) document.body.classList.remove('sidebar-enable');
      else      document.body.classList.add('sidebar-enable');
      return !prev;
    });
  }

  return (
    <LayoutContext.Provider value={{
      menu:         { size: menuSize },
      changeMenu:   { size: changeMenuSize },
      toggleBackdrop,
    }}>
      {children}
      {showBackdrop && (
        <div
          className="offcanvas-backdrop fade show"
          onClick={() => { toggleBackdrop(); changeMenuSize('default'); }}
        />
      )}
    </LayoutContext.Provider>
  );
}

export function useLayoutContext() {
  return useContext(LayoutContext);
}
