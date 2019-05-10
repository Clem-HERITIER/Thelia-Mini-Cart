import React from "react";
import "./Header.css";




function Header() {
  return (
    <div className="headMiniCart">
      <h2>Panier</h2>
      <div data-close-cart>
        <svg className="icon-close">
          <use href="/ressources/svg/sprite.svg#close"></use>
        </svg>
      </div>
    </div>
  );
}

export default Header;
