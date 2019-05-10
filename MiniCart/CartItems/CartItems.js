import React from 'react';
import './CartItems.css';
import Item from './Item/Item';


function CartItems({ data = [], handleDelete, handleUpdate }) {
    return (
        <div>
            <ul>
                {data.map((product) => {
                    return (
                        <li key={product.Id}>
                            <Item handleDelete={handleDelete} product={product} handleUpdate={handleUpdate} />
                        </li>
                    )
                })}
            </ul>
        </div>
    );

}

export default CartItems;



