import React from 'react';
import './Item.css';



function choosePrice(product) {

    let price = '';
    if (product.Promo == 1) {
        price = product.TotalTaxedPromoPrice;
        return price;
    } else {
        price = product.TotalTaxedPrice;
        return price;
    }
}

function Item({ product, handleDelete, handleUpdate }) {


    return (
        <div className='item'>
            <button onClick={() => handleDelete(product.Id)}>
                <svg className="icon-close">
                    <use href="/ressources/svg/sprite.svg#close"></use>
                </svg>
            </button>
            <img className="minicartImg" src={product.productImage} alt='' />
            <div className='attributes'>
                <a href={product.ProductUrl}> <h3>{product.ProductName}</h3></a>
                <ul className='attributes'>
                    {product.Attributes.map((attribute) => {
                        return (
                            <li key={product.Id}>
                                {attribute.AttributeValue}
                            </li>
                        )
                    })}
                </ul>
            </div>
            <div className='quantity'>
                <button onClick={() => handleUpdate(product.Id, product.Quantity - 1)}>-</button>
                <p>{product.Quantity}</p>
                <button onClick={() => handleUpdate(product.Id, product.Quantity + 1)}>+</button>
            </div>
            <p>{choosePrice(product)}</p>
        </div >
    );

}

export default Item;

