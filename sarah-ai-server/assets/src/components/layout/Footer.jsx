import React from 'react';

export default function Footer() {
  return (
    <footer className="footer">
      <div className="container-fluid">
        <div className="row">
          <div className="col-12 text-center">
            {new Date().getFullYear()} &copy; Sarah AI Server
          </div>
        </div>
      </div>
    </footer>
  );
}
